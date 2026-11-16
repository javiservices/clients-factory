package com.example.santadoor.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.security.SecureRandom
import java.security.cert.X509Certificate
import javax.net.ssl.HttpsURLConnection
import javax.net.ssl.SSLContext
import javax.net.ssl.TrustManager
import javax.net.ssl.X509TrustManager

data class CheckCardResult(
    val success: Boolean,
    val message: String,
    val warning: Boolean = false,
    val warningMessage: String? = null,
    val daysRemaining: Int? = null,
    val clientName: String? = null,
    val subscriptionType: String? = null,
    val remainingPasses: Int? = null,
    val isDailyPass: Boolean = false
)

data class UnlockResult(
    val success: Boolean,
    val message: String,
    val warning: Boolean = false,
    val warningMessage: String? = null,
    val clientName: String? = null
)

class DoorRepository {

    init {
        // Permitir certificados autofirmados / Let's Encrypt de DuckDNS en entornos locales de pruebas
        setupTrustAllSsl()
    }

    private fun setupTrustAllSsl() {
        try {
            val trustAllCerts = arrayOf<TrustManager>(object : X509TrustManager {
                override fun getAcceptedIssuers(): Array<X509Certificate>? = null
                override fun checkClientTrusted(certs: Array<X509Certificate>, authType: String) {}
                override fun checkServerTrusted(certs: Array<X509Certificate>, authType: String) {}
            })
            val sc = SSLContext.getInstance("TLS")
            sc.init(null, trustAllCerts, SecureRandom())
            HttpsURLConnection.setDefaultSSLSocketFactory(sc.socketFactory)
            HttpsURLConnection.setDefaultHostnameVerifier { _, _ -> true }
        } catch (_: Exception) {}
    }

    suspend fun checkCard(serverUrl: String, cardId: String, apiKey: String): Result<CheckCardResult> =
        withContext(Dispatchers.IO) {
            try {
                val cleanUrl = serverUrl.trimEnd('/')
                val encodedCard = URLEncoder.encode(cardId.trim(), "UTF-8")
                val endpoint = "$cleanUrl/api/check-card?card_id=$encodedCard&api_key=$apiKey"
                val url = URL(endpoint)

                val conn = (url.openConnection() as HttpURLConnection).apply {
                    requestMethod = "GET"
                    connectTimeout = 4500
                    readTimeout = 4500
                    setRequestProperty("Accept", "application/json")
                    setRequestProperty("X-Api-Key", apiKey)
                }

                val responseCode = conn.responseCode
                val stream = if (responseCode in 200..299) conn.inputStream else conn.errorStream
                val responseText = stream?.bufferedReader()?.use(BufferedReader::readText) ?: "{}"

                val json = JSONObject(responseText)
                val success = json.optBoolean("success", responseCode in 200..299)
                val message = json.optString("message", if (success) "Acceso autorizado" else "Tarjeta denegada")
                val warning = json.optBoolean("warning", false)
                val warningMessage = if (json.has("warning_message") && !json.isNull("warning_message")) {
                    json.getString("warning_message")
                } else null
                val daysRemaining = if (json.has("days_remaining") && !json.isNull("days_remaining")) {
                    json.getInt("days_remaining")
                } else null

                val clientObj = json.optJSONObject("client")
                val clientName = clientObj?.let {
                    val n = it.optString("name", "")
                    val s = it.optString("surname", "")
                    "$n $s".trim().ifEmpty { n }
                }

                val subObj = json.optJSONObject("subscription")
                val subType = if (subObj != null && subObj.has("type") && !subObj.isNull("type")) {
                    subObj.getString("type")
                } else null
                val isDaily = subObj?.optBoolean("isDailyPass", false) ?: false
                val remPasses = if (subObj != null && subObj.has("remainingPasses") && !subObj.isNull("remainingPasses")) {
                    subObj.getInt("remainingPasses")
                } else null

                Result.success(
                    CheckCardResult(
                        success = success,
                        message = message,
                        warning = warning,
                        warningMessage = warningMessage,
                        daysRemaining = daysRemaining,
                        clientName = clientName,
                        subscriptionType = subType,
                        remainingPasses = remPasses,
                        isDailyPass = isDaily
                    )
                )
            } catch (e: Exception) {
                Result.failure(e)
            }
        }

    suspend fun unlockDoor(serverUrl: String, cardId: String, apiKey: String): Result<UnlockResult> =
        withContext(Dispatchers.IO) {
            try {
                // Primero validamos la cuota con checkCard
                val checkResult = checkCard(serverUrl, cardId, apiKey).getOrThrow()
                if (!checkResult.success) {
                    return@withContext Result.success(
                        UnlockResult(
                            success = false,
                            message = checkResult.message,
                            clientName = checkResult.clientName
                        )
                    )
                }

                // Disparamos la señal de apertura remota al endpoint de apertura
                val cleanUrl = serverUrl.trimEnd('/')
                val encodedCard = URLEncoder.encode(cardId.trim(), "UTF-8")
                val openEndpoint = "$cleanUrl/api/door/open"

                val openUrl = URL(openEndpoint)
                val conn = (openUrl.openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = 4000
                    readTimeout = 4000
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("Accept", "application/json")
                    setRequestProperty("X-Api-Key", apiKey)
                }

                val payload = JSONObject().apply {
                    put("card_id", cardId.trim())
                    put("api_key", apiKey)
                    put("source", "santadoor_android")
                }.toString()

                conn.outputStream.use { os ->
                    os.write(payload.toByteArray(Charsets.UTF_8))
                }

                val code = conn.responseCode
                val stream = if (code in 200..299) conn.inputStream else conn.errorStream
                val resp = stream?.bufferedReader()?.use(BufferedReader::readText) ?: "{}"
                val json = JSONObject(resp)

                val success = json.optBoolean("success", code in 200..299)
                val msg = json.optString("message", checkResult.message)

                Result.success(
                    UnlockResult(
                        success = success,
                        message = msg,
                        warning = checkResult.warning,
                        warningMessage = checkResult.warningMessage,
                        clientName = checkResult.clientName
                    )
                )
            } catch (e: Exception) {
                // Si /api/door/open aún no está en el backend, el propio checkCard valida y autoriza la entrada
                try {
                    val fallbackCheck = checkCard(serverUrl, cardId, apiKey).getOrThrow()
                    Result.success(
                        UnlockResult(
                            success = fallbackCheck.success,
                            message = if (fallbackCheck.success) "¡Acceso autorizador verificado!" else fallbackCheck.message,
                            warning = fallbackCheck.warning,
                            warningMessage = fallbackCheck.warningMessage,
                            clientName = fallbackCheck.clientName
                        )
                    )
                } catch (ex: Exception) {
                    Result.failure(e)
                }
            }
        }
}
