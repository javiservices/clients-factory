package com.example.santadoor.data

import android.content.Context
import android.content.SharedPreferences

class DoorPreferences(context: Context) {
    private val prefs: SharedPreferences =
        context.getSharedPreferences("santadoor_prefs", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_IS_LOGGED_IN = "is_logged_in"
        private const val KEY_USER_DNI = "user_dni"
        private const val KEY_MEMBER_CODE = "member_code"
        private const val KEY_CLIENT_NAME = "client_name"
        private const val KEY_SUB_INFO = "sub_info"
        private const val KEY_DAYS_REMAINING = "days_remaining"
        private const val KEY_WARNING_MSG = "warning_msg"
        private const val KEY_HAPTIC_ENABLED = "haptic_enabled"
        private const val KEY_SERVER_URL = "server_url"

        const val DEFAULT_SERVER_URL = "https://santamariagym.duckdns.org"
        // Clave interna protegida del sistema (NUNCA expuesta en la interfaz de usuario)
        const val INTERNAL_API_KEY = "S@NT4M@R146YM"
    }

    var isLoggedIn: Boolean
        get() = prefs.getBoolean(KEY_IS_LOGGED_IN, false)
        set(value) = prefs.edit().putBoolean(KEY_IS_LOGGED_IN, value).apply()

    var userDni: String
        get() = prefs.getString(KEY_USER_DNI, "") ?: ""
        set(value) = prefs.edit().putString(KEY_USER_DNI, value.trim().uppercase()).apply()

    var memberCode: String
        get() = prefs.getString(KEY_MEMBER_CODE, "") ?: ""
        set(value) = prefs.edit().putString(KEY_MEMBER_CODE, value.trim().uppercase()).apply()

    var clientName: String
        get() = prefs.getString(KEY_CLIENT_NAME, "Socio Santamaría") ?: "Socio Santamaría"
        set(value) = prefs.edit().putString(KEY_CLIENT_NAME, value).apply()

    var subscriptionInfo: String
        get() = prefs.getString(KEY_SUB_INFO, "Cuota Activa") ?: "Cuota Activa"
        set(value) = prefs.edit().putString(KEY_SUB_INFO, value).apply()

    var daysRemaining: Int
        get() = prefs.getInt(KEY_DAYS_REMAINING, -1)
        set(value) = prefs.edit().putInt(KEY_DAYS_REMAINING, value).apply()

    var warningMessage: String?
        get() = prefs.getString(KEY_WARNING_MSG, null)
        set(value) = prefs.edit().putString(KEY_WARNING_MSG, value).apply()

    var hapticEnabled: Boolean
        get() = prefs.getBoolean(KEY_HAPTIC_ENABLED, true)
        set(value) = prefs.edit().putBoolean(KEY_HAPTIC_ENABLED, value).apply()

    var serverUrl: String
        get() = prefs.getString(KEY_SERVER_URL, DEFAULT_SERVER_URL) ?: DEFAULT_SERVER_URL
        set(value) = prefs.edit().putString(KEY_SERVER_URL, value.trim()).apply()

    // El identificador utilizado para interactuar con los tornos RFID/NFC/QR
    val cardId: String
        get() = memberCode.ifEmpty { userDni }

    // Clave de API interna (acceso de solo lectura dentro de la app)
    val apiKey: String
        get() = INTERNAL_API_KEY

    fun saveLogin(
        name: String,
        dni: String,
        code: String,
        subType: String,
        days: Int?,
        warning: String?
    ) {
        prefs.edit()
            .putBoolean(KEY_IS_LOGGED_IN, true)
            .putString(KEY_CLIENT_NAME, name)
            .putString(KEY_USER_DNI, dni.trim().uppercase())
            .putString(KEY_MEMBER_CODE, code.trim().uppercase())
            .putString(KEY_SUB_INFO, subType)
            .putInt(KEY_DAYS_REMAINING, days ?: -1)
            .putString(KEY_WARNING_MSG, warning)
            .apply()
    }

    fun clearLogin() {
        prefs.edit()
            .putBoolean(KEY_IS_LOGGED_IN, false)
            .remove(KEY_USER_DNI)
            .remove(KEY_MEMBER_CODE)
            .remove(KEY_CLIENT_NAME)
            .remove(KEY_SUB_INFO)
            .remove(KEY_DAYS_REMAINING)
            .remove(KEY_WARNING_MSG)
            .apply()
    }
}
