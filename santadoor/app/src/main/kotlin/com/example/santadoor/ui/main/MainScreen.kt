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
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
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
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.santadoor.R
import com.example.santadoor.ui.qr.QrCodeGenerator

// Paleta corporativa premium Santamaría Gym
val GymPrimary = Color(0xFFE11D48)       // Crimson 600
val GymPrimaryDark = Color(0xFF9F1239)   // Crimson 800
val GymPrimaryDeep = Color(0xFF4C0519)   // Crimson 950
val GymGold = Color(0xFFF59E0B)          // Amber 500
val GymGreen = Color(0xFF10B981)         // Emerald 500
val GymGreenDark = Color(0xFF047857)     // Emerald 700
val GymDarkBg = Color(0xFF090D16)        // Obsidian deep
val GymDarkBg2 = Color(0xFF0F172A)       // Slate 900
val GymCardBg = Color(0xFF131B2E)        // Navy Slate
val GymCardBorder = Color(0xFF26334D)    // Subdued border
val GymTextMuted = Color(0xFF94A3B8)     // Slate 400

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

    if (!state.isLoggedIn) {
        LoginScreen(
            state = state,
            onLogin = { dni, code -> viewModel.login(dni, code) },
            modifier = modifier
        )
    } else {
        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Image(
                                painter = painterResource(id = R.drawable.logo_santamaria),
                                contentDescription = "Logo Santamaría Gym",
                                modifier = Modifier
                                    .size(38.dp)
                                    .clip(CircleShape)
                                    .border(1.5.dp, Color(0xFFFDA4AF), CircleShape)
                            )
                            Spacer(modifier = Modifier.width(12.dp))
                            Column {
                                Text(
                                    text = "SANTAMARÍA GYM",
                                    color = Color.White,
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    letterSpacing = 0.5.sp
                                )
                                Text(
                                    text = "SantaDoor · Socio Verificado",
                                    color = GymGreen,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold
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
                // Pestañas de navegación superiores
                TabRow(
                    selectedTabIndex = state.selectedTab.coerceIn(0, 1),
                    containerColor = GymDarkBg,
                    contentColor = Color.White,
                    indicator = { tabPositions ->
                        TabRowDefaults.SecondaryIndicator(
                            Modifier.tabIndicatorOffset(tabPositions[state.selectedTab.coerceIn(0, 1)]),
                            color = GymPrimary
                        )
                    }
                ) {
                    Tab(
                        selected = state.selectedTab == 0,
                        onClick = { viewModel.selectTab(0) },
                        text = { Text("🚪 Torno", fontWeight = FontWeight.Bold, fontSize = 13.sp) }
                    )
                    Tab(
                        selected = state.selectedTab == 1,
                        onClick = { viewModel.selectTab(1) },
                        text = { Text("👤 Mi Perfil", fontWeight = FontWeight.Bold, fontSize = 13.sp) }
                    )
                }

                // Contenido según pestaña
                when (state.selectedTab) {
                    0 -> DoorTab(state = state, onOpenClick = { viewModel.openDoor(vibrateSuccess) })
                    else -> ProfileTab(
                        state = state,
                        onLogout = viewModel::logout,
                        onRefresh = viewModel::checkStatus,
                        onToggleHaptic = viewModel::toggleHaptic
                    )
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pantalla de Inicio de Sesión de Socios (DNI + Código)
// ─────────────────────────────────────────────────────────────────────────────

@Composable
private fun LoginScreen(
    state: MainUiState,
    onLogin: (String, String) -> Unit,
    modifier: Modifier = Modifier
) {
    var dni by remember { mutableStateOf(state.userDni) }
    var code by remember { mutableStateOf(state.memberCode) }
    val focusManager = LocalFocusManager.current

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(GymDarkBg, GymDarkBg2, Color(0xFF030712))
                )
            )
            .padding(24.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Emblema Santamaría Gym
            Image(
                painter = painterResource(id = R.drawable.logo_santamaria),
                contentDescription = "Logo Santamaría Gym",
                modifier = Modifier
                    .size(96.dp)
                    .clip(CircleShape)
                    .border(2.dp, Color(0xFFFDA4AF), CircleShape)
                    .shadow(16.dp, CircleShape, spotColor = GymPrimary)
            )

            Spacer(modifier = Modifier.height(18.dp))

            Text(
                text = "SANTAMARÍA GYM",
                color = Color.White,
                fontSize = 22.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.sp
            )
            Text(
                text = "SantaDoor · Control de Acceso",
                color = GymTextMuted,
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium
            )

            Spacer(modifier = Modifier.height(32.dp))

            // Tarjeta de Formulario de Login
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = GymCardBg),
                border = androidx.compose.foundation.BorderStroke(1.dp, GymCardBorder)
            ) {
                Column(modifier = Modifier.padding(22.dp)) {
                    Text(
                        text = "Acceso para Socios",
                        color = Color.White,
                        fontSize = 17.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "Introduce tu DNI y tu código de socio para activar el acceso móvil.",
                        color = GymTextMuted,
                        fontSize = 12.sp,
                        lineHeight = 16.sp
                    )

                    Spacer(modifier = Modifier.height(20.dp))

                    // Campo DNI / NIE
                    Text(
                        text = "DNI / NIE",
                        color = Color(0xFFCBD5E1),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                    Spacer(modifier = Modifier.height(6.dp))
                    OutlinedTextField(
                        value = dni,
                        onValueChange = { dni = it.uppercase() },
                        placeholder = { Text("Ej: 16555482J", color = Color(0xFF64748B)) },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            capitalization = KeyboardCapitalization.Characters,
                            imeAction = ImeAction.Next
                        ),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color.White,
                            unfocusedTextColor = Color.White,
                            focusedBorderColor = GymPrimary,
                            unfocusedBorderColor = GymCardBorder,
                            focusedContainerColor = Color(0xFF0F172A),
                            unfocusedContainerColor = Color(0xFF0F172A)
                        )
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    // Campo Código de Socio
                    Text(
                        text = "CÓDIGO DE SOCIO / TARJETA",
                        color = Color(0xFFCBD5E1),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                    Spacer(modifier = Modifier.height(6.dp))
                    OutlinedTextField(
                        value = code,
                        onValueChange = { code = it.uppercase() },
                        placeholder = { Text("Ej: 4773 o código RFID", color = Color(0xFF64748B)) },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            capitalization = KeyboardCapitalization.Characters,
                            imeAction = ImeAction.Done
                        ),
                        keyboardActions = KeyboardActions(
                            onDone = {
                                focusManager.clearFocus()
                                onLogin(dni, code)
                            }
                        ),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color.White,
                            unfocusedTextColor = Color.White,
                            focusedBorderColor = GymPrimary,
                            unfocusedBorderColor = GymCardBorder,
                            focusedContainerColor = Color(0xFF0F172A),
                            unfocusedContainerColor = Color(0xFF0F172A)
                        )
                    )

                    // Error si existe
                    if (state.loginError != null) {
                        Spacer(modifier = Modifier.height(14.dp))
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(10.dp))
                                .background(Color(0xFF450A0A))
                                .border(1.dp, Color(0xFFEF4444), RoundedCornerShape(10.dp))
                                .padding(12.dp)
                        ) {
                            Text(
                                text = "⚠️ ${state.loginError}",
                                color = Color(0xFFFCA5A5),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(24.dp))

                    // Botón de Inicio de Sesión
                    Button(
                        onClick = {
                            focusManager.clearFocus()
                            onLogin(dni, code)
                        },
                        enabled = !state.isLoggingIn,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(52.dp),
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = GymPrimary,
                            disabledContainerColor = GymPrimaryDark
                        )
                    ) {
                        if (state.isLoggingIn) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(24.dp),
                                color = Color.White,
                                strokeWidth = 2.5.dp
                            )
                        } else {
                            Text(
                                text = "ENTRAR A MI GIMNASIO",
                                fontWeight = FontWeight.Bold,
                                fontSize = 15.sp,
                                letterSpacing = 0.5.sp
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(20.dp))

            // Nota de ayuda
            Text(
                text = "¿No conoces tu código de socio? Consulta en recepción de Santamaría Gym para obtener tu identificador o vincular tu tarjeta.",
                color = Color(0xFF64748B),
                fontSize = 11.sp,
                textAlign = TextAlign.Center,
                lineHeight = 15.sp,
                modifier = Modifier.padding(horizontal = 16.dp)
            )
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pestaña 1: Abrir Torno (Botón Central de Lujo)
// ─────────────────────────────────────────────────────────────────────────────

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
        // Tarjeta superior de estado del socio
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(18.dp),
            colors = CardDefaults.cardColors(containerColor = GymCardBg),
            border = androidx.compose.foundation.BorderStroke(1.dp, GymCardBorder)
        ) {
            Column(modifier = Modifier.padding(18.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = state.clientName,
                            color = Color.White,
                            fontSize = 19.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = state.subscriptionType,
                            color = GymTextMuted,
                            fontSize = 13.sp
                        )
                    }

                    // Badge de socio activo
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(Color(0xFF064E3B))
                            .border(1.dp, GymGreen, RoundedCornerShape(999.dp))
                            .padding(horizontal = 10.dp, vertical = 4.dp)
                    ) {
                        Text(
                            text = "ACTIVO",
                            color = GymGreen,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // Estado de días o cuota
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    if (state.isDailyPass) {
                        Text(
                            text = "🎟️ Pases restantes: ${state.remainingPasses ?: 0}",
                            color = GymGold,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    } else if (state.daysRemaining != null) {
                        val isExpiringSoon = state.daysRemaining <= 3
                        val badgeColor = if (isExpiringSoon) GymGold else GymGreen
                        val text = if (state.daysRemaining == 0) "Cuota vence HOY" else "${state.daysRemaining} días restantes"
                        Text(
                            text = "⏱️ $text",
                            color = badgeColor,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    } else {
                        Text(
                            text = "Socio al corriente",
                            color = GymGreen,
                            fontSize = 12.sp
                        )
                    }

                    Text(
                        text = "Socio #${state.memberCode.ifEmpty { state.userDni }}",
                        color = GymTextMuted,
                        fontSize = 11.sp,
                        fontFamily = FontFamily.Monospace
                    )
                }
            }
        }

        // Aviso de vencimiento próximo si existe
        if (state.warningMessage != null) {
            Spacer(modifier = Modifier.height(12.dp))
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFF451A03))
                    .border(1.dp, GymGold, RoundedCornerShape(12.dp))
                    .padding(horizontal = 14.dp, vertical = 10.dp)
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("⚠️", fontSize = 16.sp)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = state.warningMessage ?: "",
                        color = Color(0xFFFDE68A),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(30.dp))

        // ─── BOTÓN CENTRAL DE LUJO CON HALO PULSANTE ───
        CentralUnlockButton(
            status = state.unlockStatus,
            isOpening = state.isOpening,
            onClick = onOpenClick
        )

        Spacer(modifier = Modifier.height(30.dp))

        // Mensaje de estado dinámico
        AnimatedVisibility(
            visible = state.unlockStatus !is UnlockStatus.Idle,
            enter = fadeIn(),
            exit = fadeOut()
        ) {
            when (val s = state.unlockStatus) {
                is UnlockStatus.Success -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(14.dp))
                            .background(Color(0xFF064E3B))
                            .border(1.dp, GymGreen, RoundedCornerShape(14.dp))
                            .padding(14.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = "✅ ¡ADELANTE! TORNO DESBLOQUEADO",
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp
                            )
                            Text(
                                text = s.message,
                                color = Color(0xFFA7F3D0),
                                fontSize = 12.sp
                            )
                        }
                    }
                }
                is UnlockStatus.Error -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(14.dp))
                            .background(Color(0xFF450A0A))
                            .border(1.dp, Color(0xFFEF4444), RoundedCornerShape(14.dp))
                            .padding(14.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = "❌ ACCESO DENEGADO",
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp
                            )
                            Text(
                                text = s.message,
                                color = Color(0xFFFCA5A5),
                                fontSize = 12.sp,
                                textAlign = TextAlign.Center
                            )
                        }
                    }
                }
                UnlockStatus.Idle -> {}
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Tarjeta informativa NFC / Accesos
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF0C1322)),
            border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFF1E293B))
        ) {
            Row(
                modifier = Modifier.padding(14.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("💡", fontSize = 20.sp)
                Spacer(modifier = Modifier.width(10.dp))
                Column {
                    Text(
                        text = "Acceso por Proximidad (NFC)",
                        color = Color.White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "También puedes acercar la parte trasera del móvil al lector del torno.",
                        color = GymTextMuted,
                        fontSize = 11.sp,
                        lineHeight = 14.sp
                    )
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Botón Circular Central con Efecto Halo y Morfología
// ─────────────────────────────────────────────────────────────────────────────

@Composable
private fun CentralUnlockButton(
    status: UnlockStatus,
    isOpening: Boolean,
    onClick: () -> Unit
) {
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 1f,
        targetValue = 1.12f,
        animationSpec = infiniteRepeatable(
            animation = tween(1200, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseScale"
    )

    val isSuccess = status is UnlockStatus.Success
    val isError = status is UnlockStatus.Error

    val targetColor1 = when {
        isSuccess -> GymGreen
        isError -> Color(0xFFB91C1C)
        else -> GymPrimary
    }
    val targetColor2 = when {
        isSuccess -> GymGreenDark
        isError -> Color(0xFF7F1D1D)
        else -> GymPrimaryDark
    }

    val color1 by animateColorAsState(targetValue = targetColor1, label = "btnColor1")
    val color2 by animateColorAsState(targetValue = targetColor2, label = "btnColor2")

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier.size(240.dp)
    ) {
        // Halo exterior con pulso suave
        if (!isOpening && !isSuccess && !isError) {
            Box(
                modifier = Modifier
                    .size(230.dp)
                    .scale(pulseScale)
                    .clip(CircleShape)
                    .background(GymPrimary.copy(alpha = 0.12f))
            )
            Box(
                modifier = Modifier
                    .size(210.dp)
                    .clip(CircleShape)
                    .background(GymPrimary.copy(alpha = 0.20f))
            )
        }

        // Botón principal
        Box(
            modifier = Modifier
                .size(190.dp)
                .clip(CircleShape)
                .background(
                    Brush.radialGradient(listOf(color1, color2, Color(0xFF1E030B)))
                )
                .border(3.dp, color1.copy(alpha = 0.8f), CircleShape)
                .shadow(24.dp, CircleShape, spotColor = color1)
                .clickable(enabled = !isOpening && !isSuccess, onClick = onClick),
            contentAlignment = Alignment.Center
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center
            ) {
                when {
                    isOpening -> {
                        CircularProgressIndicator(
                            modifier = Modifier.size(54.dp),
                            color = Color.White,
                            strokeWidth = 4.dp
                        )
                        Spacer(modifier = Modifier.height(10.dp))
                        Text(
                            text = "ABRIENDO...",
                            color = Color.White,
                            fontWeight = FontWeight.Black,
                            fontSize = 13.sp,
                            letterSpacing = 1.sp
                        )
                    }
                    isSuccess -> {
                        Text(text = "✓", color = Color.White, fontSize = 56.sp, fontWeight = FontWeight.Black)
                        Text(
                            text = "¡ABIERTO!",
                            color = Color.White,
                            fontWeight = FontWeight.Black,
                            fontSize = 13.sp,
                            letterSpacing = 1.sp
                        )
                    }
                    isError -> {
                        Text(text = "✕", color = Color.White, fontSize = 52.sp, fontWeight = FontWeight.Black)
                        Text(
                            text = "DENEGADO",
                            color = Color.White,
                            fontWeight = FontWeight.Black,
                            fontSize = 12.sp,
                            letterSpacing = 1.sp
                        )
                    }
                    else -> {
                        Text(text = "🚪", fontSize = 48.sp)
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "PULSA PARA",
                            color = Color.White.copy(alpha = 0.85f),
                            fontWeight = FontWeight.Bold,
                            fontSize = 11.sp,
                            letterSpacing = 1.sp
                        )
                        Text(
                            text = "ABRIR",
                            color = Color.White,
                            fontWeight = FontWeight.Black,
                            fontSize = 18.sp,
                            letterSpacing = 1.5.sp
                        )
                    }
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pestaña 2: Pase QR de Acceso
// ─────────────────────────────────────────────────────────────────────────────

@Composable
private fun QrTab(state: MainUiState) {
    val qrBitmap = remember(state.cardId) {
        QrCodeGenerator.generateQrBitmap(state.cardId.ifEmpty { state.userDni }, 600)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = "Pase Digital de Acceso",
            color = Color.White,
            fontSize = 19.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Presenta este código frente al escáner óptico del torno",
            color = GymTextMuted,
            fontSize = 12.sp,
            textAlign = TextAlign.Center
        )

        Spacer(modifier = Modifier.height(24.dp))

        // Contenedor del código QR
        Box(
            modifier = Modifier
                .size(280.dp)
                .clip(RoundedCornerShape(24.dp))
                .background(Color.White)
                .border(4.dp, GymPrimary, RoundedCornerShape(24.dp))
                .shadow(16.dp, RoundedCornerShape(24.dp), spotColor = GymPrimary),
            contentAlignment = Alignment.Center
        ) {
            if (qrBitmap != null) {
                Image(
                    bitmap = qrBitmap,
                    contentDescription = "Pase QR",
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp)
                )
            } else {
                CircularProgressIndicator(color = GymPrimary)
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        Text(
            text = state.clientName,
            color = Color.White,
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "ID Socio: ${state.memberCode.ifEmpty { state.userDni }}",
            color = GymGold,
            fontSize = 14.sp,
            fontFamily = FontFamily.Monospace,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = state.subscriptionType,
            color = GymTextMuted,
            fontSize = 12.sp
        )
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Pestaña 3: Mi Perfil / Ajustes de Socio (SIN CLAVE API VISIBLE)
// ─────────────────────────────────────────────────────────────────────────────

@Composable
private fun ProfileTab(
    state: MainUiState,
    onLogout: () -> Unit,
    onRefresh: () -> Unit,
    onToggleHaptic: (Boolean) -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp)
    ) {
        Text(
            text = "Mi Cuenta de Socio",
            color = Color.White,
            fontSize = 19.sp,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Información de tu suscripción y control de acceso",
            color = GymTextMuted,
            fontSize = 12.sp
        )

        Spacer(modifier = Modifier.height(20.dp))

        // Tarjeta de perfil del socio
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = GymCardBg),
            border = androidx.compose.foundation.BorderStroke(1.dp, GymCardBorder)
        ) {
            Column(modifier = Modifier.padding(18.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(52.dp)
                            .clip(CircleShape)
                            .background(
                                Brush.linearGradient(listOf(GymPrimary, GymPrimaryDark))
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        val initials = state.clientName
                            .split(" ")
                            .filter { it.isNotBlank() }
                            .take(2)
                            .mapNotNull { it.firstOrNull()?.toString() }
                            .joinToString("")
                            .ifEmpty { "S" }
                        Text(
                            text = initials.uppercase(),
                            color = Color.White,
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp
                        )
                    }

                    Spacer(modifier = Modifier.width(14.dp))

                    Column {
                        Text(
                            text = state.clientName,
                            color = Color.White,
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "DNI: ${state.userDni.ifEmpty { state.cardId }}",
                            color = GymTextMuted,
                            fontSize = 13.sp,
                            fontFamily = FontFamily.Monospace
                        )
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))
                Box(modifier = Modifier.fillMaxWidth().height(1.dp).background(GymCardBorder))
                Spacer(modifier = Modifier.height(14.dp))

                // Fila Código
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Código de Socio", color = GymTextMuted, fontSize = 13.sp)
                    Text(
                        text = "#${state.memberCode.ifEmpty { state.cardId }}",
                        color = Color.White,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace,
                        fontSize = 13.sp
                    )
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Fila Cuota
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Tipo de Cuota", color = GymTextMuted, fontSize = 13.sp)
                    Text(
                        text = state.subscriptionType,
                        color = GymGreen,
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.sp
                    )
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Fila Días restantes
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Vigencia", color = GymTextMuted, fontSize = 13.sp)
                    Text(
                        text = if (state.daysRemaining != null) "${state.daysRemaining} días restantes" else "En vigor",
                        color = Color.White,
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 13.sp
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Preferencias de la aplicación
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = GymCardBg),
            border = androidx.compose.foundation.BorderStroke(1.dp, GymCardBorder)
        ) {
            Column(modifier = Modifier.padding(18.dp)) {
                Text(
                    text = "Preferencias",
                    color = Color.White,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold
                )

                Spacer(modifier = Modifier.height(14.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(1f)) {
                        Text("Vibración háptica", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        Text("Vibrar al abrir el torno con éxito", color = GymTextMuted, fontSize = 11.sp)
                    }
                    Switch(
                        checked = state.hapticEnabled,
                        onCheckedChange = onToggleHaptic,
                        colors = SwitchDefaults.colors(
                            checkedThumbColor = Color.White,
                            checkedTrackColor = GymPrimary,
                            uncheckedThumbColor = Color(0xFF64748B),
                            uncheckedTrackColor = Color(0xFF1E293B)
                        )
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Botón Sincronizar estado
        Button(
            onClick = onRefresh,
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF1E293B))
        ) {
            Text("🔄 Sincronizar Estado de Cuota", color = Color.White, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
        }

        Spacer(modifier = Modifier.height(12.dp))

        // Botón Cerrar Sesión
        OutlinedButton(
            onClick = onLogout,
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp),
            shape = RoundedCornerShape(12.dp),
            border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFEF4444)),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = Color(0xFFEF4444))
        ) {
            Text("🚪 Cerrar Sesión en este Dispositivo", fontWeight = FontWeight.Bold, fontSize = 13.sp)
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Pie de seguridad informativa
        Text(
            text = "SantaDoor 2.0 · Conexión directa y cifrada con santamariagym.duckdns.org\nTus credenciales se almacenan localmente y protegidas en el almacén seguro de Android.",
            color = Color(0xFF64748B),
            fontSize = 11.sp,
            textAlign = TextAlign.Center,
            lineHeight = 15.sp,
            modifier = Modifier.fillMaxWidth()
        )
    }
}
