package com.example.santadoor.ui.main

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.TabRowDefaults
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.santadoor.ui.qr.QrCodeGenerator

// Paleta corporativa Santamaría Gym
val GymRed = Color(0xFFD72638)
val GymDarkRed = Color(0xFF8B1522)
val GymGold = Color(0xFFF59E0B)
val GymGreen = Color(0xFF10B981)
val GymDarkBg = Color(0xFF0F172A)
val GymCardBg = Color(0xFF1E293B)
val GymCardBorder = Color(0xFF334155)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen(
    modifier: Modifier = Modifier,
    viewModel: MainScreenViewModel = viewModel()
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val context = LocalContext.current

    val vibrateSuccess: () -> Unit = {
        try {
            val vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                (context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager)?.defaultVibrator
            } else {
                @Suppress("DEPRECATION")
                context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator?.vibrate(VibrationEffect.createWaveform(longArrayOf(0, 120, 80, 200), -1))
            } else {
                @Suppress("DEPRECATION")
                vibrator?.vibrate(300)
            }
        } catch (_: Exception) {}
        Unit
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(GymRed),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = "S",
                                color = Color.White,
                                fontWeight = FontWeight.Black,
                                fontSize = 18.sp
                            )
                        }
                        Spacer(modifier = Modifier.width(10.dp))
                        Column {
                            Text(
                                text = "SANTAMARÍA GYM",
                                color = Color.White,
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 0.5.sp
                            )
                            Text(
                                text = "SantaDoor · Control de Acceso",
                                color = Color(0xFF94A3B8),
                                fontSize = 11.sp
                            )
                        }
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.checkStatus() }) {
                        if (state.isChecking) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = GymGold,
                                strokeWidth = 2.dp
                            )
                        } else {
                            Text("🔄", fontSize = 18.sp)
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = GymDarkBg
                )
            )
        },
        containerColor = GymDarkBg,
        modifier = modifier
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            // Pestañas superiores
            TabRow(
                selectedTabIndex = state.selectedTab,
                containerColor = GymDarkBg,
                contentColor = Color.White,
                indicator = { tabPositions ->
                    TabRowDefaults.SecondaryIndicator(
                        Modifier.tabIndicatorOffset(tabPositions[state.selectedTab]),
                        color = GymRed
                    )
                }
            ) {
                Tab(
                    selected = state.selectedTab == 0,
                    onClick = { viewModel.selectTab(0) },
                    text = { Text("🚪 Torno", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                )
                Tab(
                    selected = state.selectedTab == 1,
                    onClick = { viewModel.selectTab(1) },
                    text = { Text("📱 Pase QR", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                )
                Tab(
                    selected = state.selectedTab == 2,
                    onClick = { viewModel.selectTab(2) },
                    text = { Text("⚙️ Ajustes", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                )
            }

            // Contenido según pestaña
            when (state.selectedTab) {
                0 -> DoorTab(state = state, onOpenClick = { viewModel.openDoor(vibrateSuccess) })
                1 -> QrTab(state = state)
                2 -> SettingsTab(state = state, onSave = viewModel::saveSettings)
            }
        }
    }
}

@Composable
private fun DoorTab(
    state: MainUiState,
    onOpenClick: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.SpaceBetween
    ) {
        // Tarjeta de información del socio
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = GymCardBg),
            border = androidx.compose.foundation.BorderStroke(1.dp, GymCardBorder)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = state.clientName,
                            color = Color.White,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = state.subscriptionType,
                            color = Color(0xFF94A3B8),
                            fontSize = 13.sp
                        )
                    }
                    // Badge de días o pases
                    if (state.isDailyPass) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(GymGold.copy(alpha = 0.2f))
                                .padding(horizontal = 10.dp, vertical = 5.dp)
                        ) {
                            Text(
                                text = "Pases: ${state.remainingPasses ?: 0}",
                                color = GymGold,
                                fontWeight = FontWeight.Bold,
                                fontSize = 12.sp
                            )
                        }
                    } else if (state.daysRemaining != null) {
                        val isUrgent = state.daysRemaining <= 3
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(if (isUrgent) GymRed.copy(alpha = 0.2f) else GymGreen.copy(alpha = 0.2f))
                                .padding(horizontal = 10.dp, vertical = 5.dp)
                        ) {
                            Text(
                                text = if (state.daysRemaining == 0) "Vence HOY" else "${state.daysRemaining} días",
                                color = if (isUrgent) GymRed else GymGreen,
                                fontWeight = FontWeight.Bold,
                                fontSize = 12.sp
                            )
                        }
                    }
                }

                // Aviso preventivo si existe
                if (!state.warningMessage.isNullOrBlank()) {
                    Spacer(modifier = Modifier.height(12.dp))
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(Color(0xFFFEF3C7))
                            .padding(10.dp)
                    ) {
                        Text(
                            text = state.warningMessage,
                            color = Color(0xFF92400E),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(28.dp))

        // Botón Circular Central de Apertura
        val infiniteTransition = rememberInfiniteTransition(label = "pulse")
        val pulseScale by infiniteTransition.animateFloat(
            initialValue = 1f,
            targetValue = if (state.isOpening) 1.06f else 1f,
            animationSpec = infiniteRepeatable(
                animation = tween(600, easing = FastOutSlowInEasing),
                repeatMode = RepeatMode.Reverse
            ),
            label = "scale"
        )

        val buttonColor by animateColorAsState(
            targetValue = when (state.unlockStatus) {
                is UnlockStatus.Success -> GymGreen
                is UnlockStatus.Error -> GymRed
                UnlockStatus.Idle -> if (state.isOpening) GymDarkRed else GymRed
            },
            label = "btnColor"
        )

        Box(
            modifier = Modifier
                .size(230.dp)
                .scale(pulseScale)
                .shadow(elevation = 20.dp, shape = CircleShape, spotColor = buttonColor)
                .clip(CircleShape)
                .background(
                    Brush.radialGradient(
                        colors = listOf(buttonColor, buttonColor.copy(alpha = 0.75f))
                    )
                )
                .border(4.dp, Color.White.copy(alpha = 0.25f), CircleShape)
                .clickable(enabled = !state.isOpening, onClick = onOpenClick),
            contentAlignment = Alignment.Center
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
                modifier = Modifier.padding(16.dp)
            ) {
                when (val status = state.unlockStatus) {
                    is UnlockStatus.Success -> {
                        Text("🔓", fontSize = 48.sp)
                        Spacer(modifier = Modifier.height(6.dp))
                        Text(
                            text = "¡ABIERTO!",
                            color = Color.White,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Black
                        )
                        Text(
                            text = "Pase concedido",
                            color = Color.White.copy(alpha = 0.9f),
                            fontSize = 12.sp
                        )
                    }
                    is UnlockStatus.Error -> {
                        Text("❌", fontSize = 44.sp)
                        Spacer(modifier = Modifier.height(6.dp))
                        Text(
                            text = "DENEGADO",
                            color = Color.White,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = status.message,
                            color = Color.White.copy(alpha = 0.9f),
                            fontSize = 10.sp,
                            textAlign = TextAlign.Center,
                            maxLines = 2
                        )
                    }
                    UnlockStatus.Idle -> {
                        if (state.isOpening) {
                            CircularProgressIndicator(
                                color = Color.White,
                                modifier = Modifier.size(52.dp),
                                strokeWidth = 4.dp
                            )
                            Spacer(modifier = Modifier.height(10.dp))
                            Text(
                                text = "ABRIENDO...",
                                color = Color.White,
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold
                            )
                        } else {
                            Text("🚪", fontSize = 48.sp)
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "TOCAR PARA ABRIR",
                                color = Color.White,
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Black,
                                letterSpacing = 0.5.sp
                            )
                            Text(
                                text = "Abre cerradura y torno",
                                color = Color.White.copy(alpha = 0.8f),
                                fontSize = 11.sp
                            )
                        }
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(28.dp))

        // Banner informativo sobre NFC y Quick Settings
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = GymCardBg.copy(alpha = 0.6f)),
            border = androidx.compose.foundation.BorderStroke(1.dp, GymCardBorder.copy(alpha = 0.5f))
        ) {
            Row(
                modifier = Modifier.padding(14.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("⚡", fontSize = 22.sp)
                Spacer(modifier = Modifier.width(10.dp))
                Column {
                    Text(
                        text = "Apertura rápida disponible",
                        color = Color.White,
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 13.sp
                    )
                    Text(
                        text = "Añade el botón de SantaDoor a los Ajustes Rápidos de tu móvil para abrir sin desbloquear.",
                        color = Color(0xFF94A3B8),
                        fontSize = 11.sp,
                        lineHeight = 15.sp
                    )
                }
            }
        }
    }
}

@Composable
private fun QrTab(state: MainUiState) {
    val qrBitmap = remember(state.cardId) {
        QrCodeGenerator.generateQrBitmap(state.cardId, size = 600)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "Pase Digital de Acceso",
            color = Color.White,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Muestra este código al escáner óptico del torno",
            color = Color(0xFF94A3B8),
            fontSize = 13.sp,
            textAlign = TextAlign.Center
        )

        Spacer(modifier = Modifier.height(24.dp))

        // Tarjeta blanca de alto contraste para lectura rápida de escáner
        Card(
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            modifier = Modifier
                .size(280.dp)
                .shadow(16.dp, RoundedCornerShape(20.dp))
        ) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(20.dp),
                contentAlignment = Alignment.Center
            ) {
                if (qrBitmap != null) {
                    Image(
                        bitmap = qrBitmap,
                        contentDescription = "Pase QR",
                        modifier = Modifier.fillMaxSize()
                    )
                } else {
                    CircularProgressIndicator(color = GymRed)
                }
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        Text(
            text = state.clientName,
            color = Color.White,
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "ID Socio / Tarjeta: ${state.cardId}",
            color = GymGold,
            fontSize = 13.sp,
            fontFamily = FontFamily.Monospace,
            fontWeight = FontWeight.SemiBold
        )
    }
}

@Composable
private fun SettingsTab(
    state: MainUiState,
    onSave: (String, String, String) -> Unit
) {
    var serverUrl by remember(state.serverUrl) { mutableStateOf(state.serverUrl) }
    var cardId by remember(state.cardId) { mutableStateOf(state.cardId) }
    var apiKey by remember(state.apiKey) { mutableStateOf(state.apiKey) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp)
    ) {
        Text(
            text = "Configuración SantaDoor",
            color = Color.White,
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Parámetros de conexión con el torno y servidor",
            color = Color(0xFF94A3B8),
            fontSize = 12.sp
        )

        Spacer(modifier = Modifier.height(20.dp))

        // Campo Card ID
        Text("DNI / Código de Tarjeta del Socio", color = Color(0xFFCBD5E1), fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(6.dp))
        OutlinedTextField(
            value = cardId,
            onValueChange = { cardId = it },
            placeholder = { Text("Ej: 16555482J o código RFID") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color.White,
                unfocusedTextColor = Color.White,
                focusedBorderColor = GymRed,
                unfocusedBorderColor = GymCardBorder,
                focusedContainerColor = GymCardBg,
                unfocusedContainerColor = GymCardBg
            )
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Campo URL Servidor
        Text("URL del Servidor Gym", color = Color(0xFFCBD5E1), fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(6.dp))
        OutlinedTextField(
            value = serverUrl,
            onValueChange = { serverUrl = it },
            placeholder = { Text("https://santamariagym.duckdns.org") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color.White,
                unfocusedTextColor = Color.White,
                focusedBorderColor = GymRed,
                unfocusedBorderColor = GymCardBorder,
                focusedContainerColor = GymCardBg,
                unfocusedContainerColor = GymCardBg
            )
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Campo Clave API
        Text("Clave de API", color = Color(0xFFCBD5E1), fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(6.dp))
        OutlinedTextField(
            value = apiKey,
            onValueChange = { apiKey = it },
            placeholder = { Text("S@NT4M@R146YM") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color.White,
                unfocusedTextColor = Color.White,
                focusedBorderColor = GymRed,
                unfocusedBorderColor = GymCardBorder,
                focusedContainerColor = GymCardBg,
                unfocusedContainerColor = GymCardBg
            )
        )

        Spacer(modifier = Modifier.height(24.dp))

        Button(
            onClick = { onSave(serverUrl, cardId, apiKey) },
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = GymRed)
        ) {
            Text("Guardar y Probar Conexión", fontWeight = FontWeight.Bold, fontSize = 14.sp)
        }
    }
}

@androidx.compose.ui.tooling.preview.Preview(showBackground = true)
@Composable
fun MainScreenPreview() {
    com.example.santadoor.ui.theme.SantaDoorTheme {
        DoorTab(
            state = MainUiState(
                clientName = "Jesús Sáenz",
                subscriptionType = "Cuota Completa Sala",
                daysRemaining = 18,
                cardId = "16555482J"
            ),
            onOpenClick = {}
        )
    }
}
