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
    val isLoggedIn: Boolean = false,
    val serverUrl: String = DoorPreferences.DEFAULT_SERVER_URL,
    val userDni: String = "",
    val memberCode: String = "",
    val cardId: String = "",
    val clientName: String = "Socio Santamaría",
    val subscriptionType: String = "Consultando...",
    val daysRemaining: Int? = null,
    val warningMessage: String? = null,
    val remainingPasses: Int? = null,
    val isDailyPass: Boolean = false,
    val isOpening: Boolean = false,
    val isChecking: Boolean = false,
    val isLoggingIn: Boolean = false,
    val loginError: String? = null,
    val unlockStatus: UnlockStatus = UnlockStatus.Idle,
    val hapticEnabled: Boolean = true,
    val selectedTab: Int = 0 // 0: Torno, 1: Mi Perfil
)

class MainScreenViewModel(application: Application) : AndroidViewModel(application) {

    private val prefs = DoorPreferences(application)
    private val repository = DoorRepository()

    private val _uiState = MutableStateFlow(
        MainUiState(
            isLoggedIn = prefs.isLoggedIn,
            serverUrl = prefs.serverUrl,
            userDni = prefs.userDni,
            memberCode = prefs.memberCode,
            cardId = prefs.cardId,
            clientName = prefs.clientName,
            subscriptionType = prefs.subscriptionInfo,
            daysRemaining = if (prefs.daysRemaining >= 0) prefs.daysRemaining else null,
            warningMessage = prefs.warningMessage,
            hapticEnabled = prefs.hapticEnabled
        )
    )
    val uiState: StateFlow<MainUiState> = _uiState.asStateFlow()

    init {
        if (prefs.isLoggedIn) {
            checkStatus()
        }
    }

    fun selectTab(index: Int) {
        _uiState.update { it.copy(selectedTab = index) }
    }

    fun login(dni: String, code: String) {
        val cleanDni = dni.trim().uppercase()
        val cleanCode = code.trim().uppercase()

        if (cleanDni.isEmpty() || cleanCode.isEmpty()) {
            _uiState.update { it.copy(loginError = "Por favor introduce tu DNI y tu código de socio.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoggingIn = true, loginError = null) }
            val server = _uiState.value.serverUrl
            val result = repository.login(server, cleanDni, cleanCode)

            result.onSuccess { data ->
                prefs.saveLogin(
                    name = data.clientName,
                    dni = data.dni,
                    code = data.cardCode,
                    subType = data.subscriptionType,
                    days = data.daysRemaining,
                    warning = data.warningMessage
                )

                _uiState.update {
                    it.copy(
                        isLoggedIn = true,
                        isLoggingIn = false,
                        loginError = null,
                        userDni = data.dni,
                        memberCode = data.cardCode,
                        cardId = data.cardCode.ifEmpty { data.dni },
                        clientName = data.clientName,
                        subscriptionType = data.subscriptionType,
                        daysRemaining = data.daysRemaining,
                        warningMessage = data.warningMessage,
                        remainingPasses = data.remainingPasses,
                        isDailyPass = data.isDailyPass,
                        selectedTab = 0
                    )
                }
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isLoggingIn = false,
                        loginError = err.message ?: "No se pudo verificar el acceso. Comprueba tus datos."
                    )
                }
            }
        }
    }

    fun logout() {
        prefs.clearLogin()
        _uiState.update {
            it.copy(
                isLoggedIn = false,
                userDni = "",
                memberCode = "",
                cardId = "",
                clientName = "Socio Santamaría",
                subscriptionType = "Sin cuota activa",
                daysRemaining = null,
                warningMessage = null,
                selectedTab = 0,
                loginError = null
            )
        }
    }

    fun checkStatus() {
        val currentCard = prefs.cardId
        if (currentCard.isEmpty()) return

        viewModelScope.launch {
            _uiState.update { it.copy(isChecking = true) }
            val current = _uiState.value
            val result = repository.checkCard(current.serverUrl, currentCard)

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
        val currentCard = prefs.cardId
        if (currentCard.isEmpty()) {
            _uiState.update {
                it.copy(unlockStatus = UnlockStatus.Error("Inicia sesión para abrir la puerta"))
            }
            return
        }

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isOpening = true,
                    unlockStatus = UnlockStatus.Idle
                )
            }

            val current = _uiState.value
            val result = repository.unlockDoor(current.serverUrl, currentCard)

            result.onSuccess { res ->
                if (res.success) {
                    if (prefs.hapticEnabled) {
                        onSuccessVibrate()
                    }
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
                    // Mantener estado de éxito durante 4.5 segundos
                    delay(4500)
                    _uiState.update { it.copy(unlockStatus = UnlockStatus.Idle) }
                } else {
                    _uiState.update {
                        it.copy(
                            isOpening = false,
                            unlockStatus = UnlockStatus.Error(res.message)
                        )
                    }
                    delay(3500)
                    _uiState.update { it.copy(unlockStatus = UnlockStatus.Idle) }
                }
            }.onFailure { err ->
                _uiState.update {
                    it.copy(
                        isOpening = false,
                        unlockStatus = UnlockStatus.Error("Error al contactar con el torno: ${err.message ?: "Sin red"}")
                    )
                }
                delay(3500)
                _uiState.update { it.copy(unlockStatus = UnlockStatus.Idle) }
            }
        }
    }

    fun toggleHaptic(enabled: Boolean) {
        prefs.hapticEnabled = enabled
        _uiState.update { it.copy(hapticEnabled = enabled) }
    }
}
