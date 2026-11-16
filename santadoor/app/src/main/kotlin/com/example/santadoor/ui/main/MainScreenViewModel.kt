package com.example.santadoor.ui.main

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.example.santadoor.data.DoorPreferences
import com.example.santadoor.data.DoorRepository
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

sealed interface UnlockStatus {
    object Idle : UnlockStatus
    data class Success(val message: String, val clientName: String?, val warningMessage: String?) : UnlockStatus
    data class Error(val message: String) : UnlockStatus
}

data class MainUiState(
    val serverUrl: String = DoorPreferences.DEFAULT_SERVER_URL,
    val cardId: String = DoorPreferences.DEFAULT_CARD_ID,
    val apiKey: String = DoorPreferences.DEFAULT_API_KEY,
    val clientName: String = "Socio Santamaría",
    val subscriptionType: String = "Consultando...",
    val daysRemaining: Int? = null,
    val warningMessage: String? = null,
    val remainingPasses: Int? = null,
    val isDailyPass: Boolean = false,
    val isOpening: Boolean = false,
    val isChecking: Boolean = false,
    val unlockStatus: UnlockStatus = UnlockStatus.Idle,
    val selectedTab: Int = 0 // 0: Puerta, 1: Pase QR, 2: Ajustes
)

class MainScreenViewModel(application: Application) : AndroidViewModel(application) {

    private val prefs = DoorPreferences(application)
    private val repository = DoorRepository()

    private val _uiState = MutableStateFlow(
        MainUiState(
            serverUrl = prefs.serverUrl,
            cardId = prefs.cardId,
            apiKey = prefs.apiKey,
            clientName = prefs.clientName,
            subscriptionType = prefs.subscriptionInfo,
            daysRemaining = if (prefs.daysRemaining >= 0) prefs.daysRemaining else null,
            warningMessage = prefs.warningMessage
        )
    )
    val uiState: StateFlow<MainUiState> = _uiState.asStateFlow()

    init {
        checkStatus()
    }

    fun selectTab(index: Int) {
        _uiState.update { it.copy(selectedTab = index) }
    }

    fun checkStatus() {
        viewModelScope.launch {
            _uiState.update { it.copy(isChecking = true) }
            val current = _uiState.value
            val result = repository.checkCard(current.serverUrl, current.cardId, current.apiKey)

            result.onSuccess { data ->
                val name = data.clientName ?: current.clientName
                val sub = data.subscriptionType ?: current.subscriptionType

                prefs.clientName = name
                prefs.subscriptionInfo = sub
                data.daysRemaining?.let { prefs.daysRemaining = it }
                prefs.warningMessage = data.warningMessage

                _uiState.update {
                    it.copy(
                        isChecking = false,
                        clientName = name,
                        subscriptionType = sub,
                        daysRemaining = data.daysRemaining,
                        warningMessage = data.warningMessage,
                        remainingPasses = data.remainingPasses,
                        isDailyPass = data.isDailyPass
                    )
                }
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isChecking = false,
                        warningMessage = "Sin conexión con servidor: ${err.localizedMessage ?: "Error de red"}"
                    )
                }
            }
        }
    }

    fun openDoor(onSuccessVibrate: () -> Unit) {
        if (_uiState.value.isOpening) return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isOpening = true,
                    unlockStatus = UnlockStatus.Idle
                )
            }

            val current = _uiState.value
            val result = repository.unlockDoor(current.serverUrl, current.cardId, current.apiKey)

            result.onSuccess { res ->
                if (res.success) {
                    onSuccessVibrate()
                    _uiState.update {
                        it.copy(
                            isOpening = false,
                            unlockStatus = UnlockStatus.Success(
                                message = res.message,
                                clientName = res.clientName ?: it.clientName,
                                warningMessage = res.warningMessage
                            )
                        )
                    }
                    // Mantener estado de éxito durante 4 segundos
                    delay(4000)
                    _uiState.update { it.copy(unlockStatus = UnlockStatus.Idle) }
                } else {
                    _uiState.update {
                        it.copy(
                            isOpening = false,
                            unlockStatus = UnlockStatus.Error(res.message)
                        )
                    }
                    delay(3000)
                    _uiState.update { it.copy(unlockStatus = UnlockStatus.Idle) }
                }
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isOpening = false,
                        unlockStatus = UnlockStatus.Error("Error al contactar con el torno: ${err.message ?: "Sin red"}")
                    )
                }
                delay(3000)
                _uiState.update { it.copy(unlockStatus = UnlockStatus.Idle) }
            }
        }
    }

    fun saveSettings(serverUrl: String, cardId: String, apiKey: String) {
        val cleanUrl = serverUrl.trim().ifEmpty { DoorPreferences.DEFAULT_SERVER_URL }
        val cleanCard = cardId.trim().uppercase().ifEmpty { DoorPreferences.DEFAULT_CARD_ID }
        val cleanApi = apiKey.trim().ifEmpty { DoorPreferences.DEFAULT_API_KEY }

        prefs.serverUrl = cleanUrl
        prefs.cardId = cleanCard
        prefs.apiKey = cleanApi

        _uiState.update {
            it.copy(
                serverUrl = cleanUrl,
                cardId = cleanCard,
                apiKey = cleanApi,
                selectedTab = 0
            )
        }
        checkStatus()
    }
}
