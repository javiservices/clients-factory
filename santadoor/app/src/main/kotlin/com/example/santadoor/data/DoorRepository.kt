package com.example.santadoor.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
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

data class LoginResult(
    val success: Boolean,
    val message: String,
    val clientName: String,
    val dni: String,
    val cardCode: String,
    val subscriptionType: String,
    val daysRemaining: Int?,
    val warningMessage: String?,
    val isDailyPass: Boolean = false,
    val remainingPasses: Int? = null
)

data class OccupancyResult(
    val success: Boolean = true,
    val count: Int = 0,
    val level: String = "low",
    val status: String = "Baja afluencia",
    val statusSub: String = "Momento ideal para entrenar",
    val color: String = "#10B981",
    val percentage: Int = 0,
    val updatedAt: String = ""
)

class DoorRepository {

    init {
        // Permitir certificados DuckDNS en conexiones locales de pruebas
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

    /**
     * Valida credenciales de socio: DNI + Código de socio / Tarjeta
     */
    suspend fun login(serverUrl: String, dni: String, code: String): Result<LoginResult> =
        withContext(Dispatchers.IO) {
            try {
                val cleanUrl = serverUrl.trimEnd('/')
                val endpoint = "$cleanUrl/api/client/verify-login"
                val url = URL(endpoint)
                val conn = (url.openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = 5000
                    readTimeout = 5000
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("Accept", "application/json")
                }

                val payload = JSONObject().apply {
                    put("dni", dni.trim().uppercase())
                    put("code", code.trim().uppercase())
                }.toString()

                conn.outputStream.use { os ->
                    os.write(payload.toByteArray(Charsets.UTF_8))
                }

                val responseCode = conn.responseCode
                val stream = if (responseCode in 200..299) conn.inputStream else conn.errorStream
                val responseText = stream?.bufferedReader()?.use(BufferedReader::readText) ?: "{}"
                val json = JSONObject(responseText)

                val success = json.optBoolean("success", responseCode in 200..299)
                val message = json.optString("message", if (success) "Inicio de sesión correcto" else "Datos incorrectos")

                if (!success) {
                    return@withContext Result.failure(Exception(message))
                }

                val clientObj = json.optJSONObject("client")
                val name = clientObj?.optString("name", "") ?: ""
                val surname = clientObj?.optString("surname", "") ?: ""
                val fullName = "$name $surname".trim().ifEmpty { "Socio Santamaría" }
                val clientDni = clientObj?.optString("dni", dni) ?: dni
                val cardCode = clientObj?.optString("cardCode", code) ?: code

                val subObj = json.optJSONObject("subscription")
                val subType = subObj?.optString("type", "Cuota Activa") ?: "Cuota Activa"
                val daysRemaining = if (subObj != null && subObj.has("daysRemaining") && !subObj.isNull("daysRemaining")) {
                    subObj.getInt("daysRemaining")
                } else null
                val warning = if (subObj != null && subObj.has("warningMessage") && !subObj.isNull("warningMessage")) {
                    subObj.getString("warningMessage")
                } else null
                val isDaily = subObj?.optBoolean("isDailyPass", false) ?: false
                val remainingPasses = if (subObj != null && subObj.has("remainingPasses") && !subObj.isNull("remainingPasses")) {
                    subObj.getInt("remainingPasses")
                } else null

                Result.success(
                    LoginResult(
                        success = true,
                        message = message,
                        clientName = fullName,
                        dni = clientDni,
                        cardCode = cardCode,
                        subscriptionType = subType,
                        daysRemaining = daysRemaining,
                        warningMessage = warning,
                        isDailyPass = isDaily,
                        remainingPasses = remainingPasses
                    )
                )
            } catch (e: Exception) {
                Result.failure(e)
            }
        }

    suspend fun checkCard(
        serverUrl: String,
        cardId: String,
        apiKey: String = DoorPreferences.INTERNAL_API_KEY
    ): Result<CheckCardResult> =
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

    suspend fun unlockDoor(
        serverUrl: String,
        cardId: String,
        apiKey: String = DoorPreferences.INTERNAL_API_KEY,
        latitude: Double? = null,
        longitude: Double? = null
    ): Result<UnlockResult> =
        withContext(Dispatchers.IO) {
            try {
                val cleanUrl = serverUrl.trimEnd('/')
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
                    if (latitude != null && longitude != null) {
                        put("latitude", latitude)
                        put("longitude", longitude)
                    }
                }.toString()

                conn.outputStream.use { os ->
                    os.write(payload.toByteArray(Charsets.UTF_8))
                }

                val code = conn.responseCode
                val stream = if (code in 200..299) conn.inputStream else conn.errorStream
                val resp = stream?.bufferedReader()?.use(BufferedReader::readText) ?: "{}"
                val json = JSONObject(resp)

                val success = json.optBoolean("success", code in 200..299)
                val msg = json.optString("message", if (success) "¡Apertura autorizada! Torno activado." else "Acceso denegado")

                Result.success(
                    UnlockResult(
                        success = success,
                        message = msg,
                        clientName = json.optJSONObject("client")?.optString("name")
                    )
                )
            } catch (e: Exception) {
                Result.failure(e)
            }
        }

    suspend fun getOccupancy(serverUrl: String): Result<OccupancyResult> =
        withContext(Dispatchers.IO) {
            try {
                val cleanUrl = serverUrl.trimEnd('/')
                val endpoint = "$cleanUrl/api/gym/occupancy"
                val url = URL(endpoint)
                val conn = (url.openConnection() as HttpURLConnection).apply {
                    requestMethod = "GET"
                    connectTimeout = 4000
                    readTimeout = 4000
                    setRequestProperty("Accept", "application/json")
                }

                val code = conn.responseCode
                val stream = if (code in 200..299) conn.inputStream else conn.errorStream
                val resp = stream?.bufferedReader()?.use(BufferedReader::readText) ?: "{}"
                val json = JSONObject(resp)

                val success = json.optBoolean("success", code in 200..299)
                val count = json.optInt("count", 0)
                val level = json.optString("level", "low")
                val status = json.optString("status", "Baja afluencia")
                val statusSub = json.optString("statusSub", "Momento ideal para entrenar")
                val color = json.optString("color", "#10B981")
                val percentage = json.optInt("percentage", 10)
                val updatedAt = json.optString("updatedAt", "")

                Result.success(
                    OccupancyResult(
                        success = success,
                        count = count,
                        level = level,
                        status = status,
                        statusSub = statusSub,
                        color = color,
                        percentage = percentage,
                        updatedAt = updatedAt
                    )
                )
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
}
