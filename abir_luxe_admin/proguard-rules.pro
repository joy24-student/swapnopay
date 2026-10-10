# ProGuard rules for Abir Luxe Shop Native WebView App
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}
-keep class com.abirluxe.shop.bridge.** { *; }
-dontwarn okhttp3.**
-dontwarn okio.**
