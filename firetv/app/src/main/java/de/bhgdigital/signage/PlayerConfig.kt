package de.bhgdigital.signage

import android.content.Context
import java.io.RandomAccessFile

/**
 * Liest die beim Download injizierte Player-URL aus der eigenen APK
 * (Trailer am Dateiende: [url][länge:4 LE][MAGIC:8], siehe ApkUrlInjector.php)
 * und merkt sie sich in den SharedPreferences.
 */
object PlayerConfig {

    private const val PREFS = "signage"
    private const val KEY_URL = "player_url"
    private const val MAGIC = "SIGNGURL"

    fun resolveUrl(context: Context): String? {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        prefs.getString(KEY_URL, null)?.let { return it }

        val baked = readBakedUrl(context)
        if (!baked.isNullOrBlank()) {
            prefs.edit().putString(KEY_URL, baked).apply()
            return baked
        }
        return null
    }

    fun saveUrl(context: Context, url: String) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putString(KEY_URL, url.trim()).apply()
    }

    fun currentUrl(context: Context): String? =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY_URL, null)

    private fun readBakedUrl(context: Context): String? {
        return try {
            val path = context.applicationInfo.sourceDir
            RandomAccessFile(path, "r").use { raf ->
                val len = raf.length()
                if (len < 12) return null

                val magic = ByteArray(8)
                raf.seek(len - 8)
                raf.readFully(magic)
                if (String(magic, Charsets.US_ASCII) != MAGIC) return null

                val lenBuf = ByteArray(4)
                raf.seek(len - 12)
                raf.readFully(lenBuf)
                val urlLen = (lenBuf[0].toInt() and 0xFF) or
                    ((lenBuf[1].toInt() and 0xFF) shl 8) or
                    ((lenBuf[2].toInt() and 0xFF) shl 16) or
                    ((lenBuf[3].toInt() and 0xFF) shl 24)
                if (urlLen <= 0 || urlLen > 4096 || len < 12 + urlLen) return null

                val urlBuf = ByteArray(urlLen)
                raf.seek(len - 12 - urlLen)
                raf.readFully(urlBuf)
                String(urlBuf, Charsets.UTF_8)
            }
        } catch (e: Exception) {
            null
        }
    }
}
