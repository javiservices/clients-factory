package com.example.santadoor.data

import android.content.Context
import android.content.SharedPreferences

class DoorPreferences(context: Context) {
    private val prefs: SharedPreferences =
        context.getSharedPreferences("santadoor_prefs", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_SERVER_URL = "server_url"
        private const val KEY_CARD_ID = "card_id"
        private const val KEY_API_KEY = "api_key"
        private const val KEY_CLIENT_NAME = "client_name"
        private const val KEY_SUB_INFO = "sub_info"
        private const val KEY_DAYS_REMAINING = "days_remaining"
        private const val KEY_WARNING_MSG = "warning_msg"
        private const val KEY_HAPTIC_ENABLED = "haptic_enabled"

        const val DEFAULT_SERVER_URL = "https://santamariagym.duckdns.org"
        const val DEFAULT_API_KEY = "S@NT4M@R146YM"
        const val DEFAULT_CARD_ID = "16555482J"
    }

    var serverUrl: String
        get() = prefs.getString(KEY_SERVER_URL, DEFAULT_SERVER_URL) ?: DEFAULT_SERVER_URL
        set(value) = prefs.edit().putString(KEY_SERVER_URL, value.trim()).apply()

    var cardId: String
        get() = prefs.getString(KEY_CARD_ID, DEFAULT_CARD_ID) ?: DEFAULT_CARD_ID
        set(value) = prefs.edit().putString(KEY_CARD_ID, value.trim().uppercase()).apply()

    var apiKey: String
        get() = prefs.getString(KEY_API_KEY, DEFAULT_API_KEY) ?: DEFAULT_API_KEY
        set(value) = prefs.edit().putString(KEY_API_KEY, value.trim()).apply()

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
}
