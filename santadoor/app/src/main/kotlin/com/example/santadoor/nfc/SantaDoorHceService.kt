package com.example.santadoor.nfc

import android.nfc.cardemulation.HostApduService
import android.os.Bundle
import android.util.Log
import com.example.santadoor.data.DoorPreferences

class SantaDoorHceService : HostApduService() {

    companion object {
        private const val TAG = "SantaDoorHce"

        // ISO 7816-4 Status Words
        private val STATUS_SUCCESS = byteArrayOf(0x90.toByte(), 0x00.toByte())
        private val STATUS_FAILED = byteArrayOf(0x6F.toByte(), 0x00.toByte())
        private val STATUS_CLA_NOT_SUPPORTED = byteArrayOf(0x6E.toByte(), 0x00.toByte())
    }

    override fun processCommandApdu(commandApdu: ByteArray?, extras: Bundle?): ByteArray {
        if (commandApdu == null || commandApdu.size < 4) {
            return STATUS_FAILED
        }

        val prefs = DoorPreferences(applicationContext)
        val cardId = prefs.cardId
        Log.i(TAG, "APDU recibido de lector. Longitud: ${commandApdu.size}, respondiendo con Card ID: $cardId")

        // 1. SELECT AID (00 A4 04 00 ...)
        if (commandApdu[0] == 0x00.toByte() && commandApdu[1] == 0xA4.toByte() && commandApdu[2] == 0x04.toByte()) {
            val payload = "SANTADOOR:$cardId".toByteArray(Charsets.UTF_8)
            return payload + STATUS_SUCCESS
        }

        // 2. READ BINARY / GET DATA (00 B0 ... o 00 CA ...)
        if (commandApdu[1] == 0xB0.toByte() || commandApdu[1] == 0xCA.toByte()) {
            val payload = cardId.toByteArray(Charsets.UTF_8)
            return payload + STATUS_SUCCESS
        }

        // Para cualquier otro comando estándar del lector, devolvemos el card_id en UTF-8 + 90 00
        val payload = cardId.toByteArray(Charsets.UTF_8)
        return payload + STATUS_SUCCESS
    }

    override fun onDeactivated(reason: Int) {
        val reasonStr = when (reason) {
            DEACTIVATION_LINK_LOSS -> "Conexión NFC perdida"
            DEACTIVATION_DESELECTED -> "Deseleccionado por lector"
            else -> "Razón $reason"
        }
        Log.d(TAG, "HCE Desactivado: $reasonStr")
    }
}

