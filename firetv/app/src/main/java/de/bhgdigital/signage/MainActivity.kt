package de.bhgdigital.signage

import android.annotation.SuppressLint
import android.os.Bundle
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.EditText
import android.widget.FrameLayout
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity

/**
 * Kiosk-Player: lädt die (per Download injizierte bzw. konfigurierte) Player-URL
 * im Vollbild. Hält den Bildschirm wach, blendet die System-UI aus und lädt bei
 * Fehlern automatisch neu. Über die MENU-Taste der Fernbedienung lässt sich die
 * URL nachträglich ändern.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private val reloadHandler = android.os.Handler(android.os.Looper.getMainLooper())
    private var fullscreenView: View? = null
    private var fullscreenCallback: WebChromeClient.CustomViewCallback? = null

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        webView = WebView(this).apply {
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
            keepScreenOn = true
            settings.apply {
                javaScriptEnabled = true
                domStorageEnabled = true                 // localStorage -> Pairing/device_token
                mediaPlaybackRequiresUserGesture = false  // Autoplay (Video/Audio)
                loadWithOverviewMode = true
                useWideViewPort = true
                cacheMode = android.webkit.WebSettings.LOAD_DEFAULT
            }
            webViewClient = object : WebViewClient() {
                override fun onReceivedError(
                    view: WebView?, request: android.webkit.WebResourceRequest?,
                    error: android.webkit.WebResourceError?
                ) {
                    // Nur Fehler der Hauptseite -> nach kurzer Pause neu laden.
                    if (request?.isForMainFrame == true) scheduleReload()
                }
            }
            webChromeClient = object : WebChromeClient() {
                override fun onShowCustomView(view: View?, callback: CustomViewCallback?) {
                    enterFullscreenVideo(view, callback)
                }
                override fun onHideCustomView() {
                    exitFullscreenVideo()
                }
            }
        }
        setContentView(webView)

        val url = PlayerConfig.resolveUrl(this)
        if (url.isNullOrBlank()) {
            showUrlDialog(prefill = "https://")
        } else {
            webView.loadUrl(url)
        }
    }

    private fun scheduleReload() {
        reloadHandler.removeCallbacksAndMessages(null)
        reloadHandler.postDelayed({
            PlayerConfig.currentUrl(this)?.let { webView.loadUrl(it) }
        }, 5000)
    }

    private fun enterFullscreenVideo(view: View?, callback: WebChromeClient.CustomViewCallback?) {
        if (fullscreenView != null) {
            callback?.onCustomViewHidden(); return
        }
        fullscreenView = view
        fullscreenCallback = callback
        (window.decorView as FrameLayout).addView(
            view,
            FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
        )
    }

    private fun exitFullscreenVideo() {
        fullscreenView?.let { (window.decorView as FrameLayout).removeView(it) }
        fullscreenView = null
        fullscreenCallback?.onCustomViewHidden()
        fullscreenCallback = null
        hideSystemUi()
    }

    /** Konfig-Dialog (z.B. via MENU-Taste) zum Setzen/Ändern der Player-URL. */
    private fun showUrlDialog(prefill: String) {
        val input = EditText(this).apply {
            setText(prefill)
            setSelection(text.length)
        }
        AlertDialog.Builder(this)
            .setTitle(R.string.config_title)
            .setMessage(R.string.config_message)
            .setView(input)
            .setPositiveButton(android.R.string.ok) { _, _ ->
                val value = input.text.toString().trim()
                if (value.startsWith("http")) {
                    PlayerConfig.saveUrl(this, value)
                    webView.loadUrl(value)
                }
            }
            .setNegativeButton(android.R.string.cancel, null)
            .setCancelable(true)
            .show()
    }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        // MENU -> URL ändern; BACK im Kiosk neutralisieren (kein Verlassen).
        when (keyCode) {
            KeyEvent.KEYCODE_MENU -> {
                showUrlDialog(prefill = PlayerConfig.currentUrl(this) ?: "https://")
                return true
            }
            KeyEvent.KEYCODE_BACK -> return true
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus) hideSystemUi()
    }

    @Suppress("DEPRECATION")
    private fun hideSystemUi() {
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                or View.SYSTEM_UI_FLAG_FULLSCREEN
                or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                or View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                or View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
            )
    }

    override fun onDestroy() {
        reloadHandler.removeCallbacksAndMessages(null)
        super.onDestroy()
    }
}
