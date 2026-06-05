package de.bhgdigital.signage

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Startet den Player automatisch nach dem Hochfahren (z.B. nach Stromausfall).
 */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent?) {
        val launch = Intent(context, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        context.startActivity(launch)
    }
}
