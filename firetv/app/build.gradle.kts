plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "de.bhgdigital.signage"
    compileSdk = 34

    defaultConfig {
        applicationId = "de.bhgdigital.signage"
        minSdk = 21
        // Bewusst < 30: erlaubt v1-only-Signatur beim Sideload (Voraussetzung
        // für die URL-Injektion in den APK-Kommentar, siehe ApkUrlInjector).
        targetSdk = 29
        versionCode = 1
        versionName = "1.0"
    }

    signingConfigs {
        create("release") {
            val ksPath = (findProperty("KEYSTORE_FILE") as String?) ?: System.getenv("KEYSTORE_FILE")
            if (ksPath != null) {
                storeFile = file(ksPath)
                storePassword = (findProperty("KEYSTORE_PASSWORD") as String?) ?: System.getenv("KEYSTORE_PASSWORD")
                keyAlias = (findProperty("KEY_ALIAS") as String?) ?: System.getenv("KEY_ALIAS")
                keyPassword = (findProperty("KEY_PASSWORD") as String?) ?: System.getenv("KEY_PASSWORD")
            }
            // WICHTIG: nur v1 (JAR) signieren – v2/v3 würden den injizierten
            // APK-Kommentar als manipuliert erkennen.
            enableV1Signing = true
            enableV2Signing = false
            enableV3Signing = false
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            val ksPath = (findProperty("KEYSTORE_FILE") as String?) ?: System.getenv("KEYSTORE_FILE")
            if (ksPath != null) {
                signingConfig = signingConfigs.getByName("release")
            }
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    lint {
        // Bewusst targetSdk 29 (v1-only-Signatur fuer Sideload + Kommentar-Injektion).
        // Der Play-"ExpiredTargetSdkVersion"-Check ist hier nicht relevant.
        disable += "ExpiredTargetSdkVersion"
        checkReleaseBuilds = false
        abortOnError = false
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.6.1")
}
