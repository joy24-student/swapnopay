package com.example.data.remote

import android.util.Log
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.TimeUnit

object OpenRouterClient {

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .build()

    private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()
    private const val OPENROUTER_URL = "https://openrouter.ai/api/v1/chat/completions"

    val FREE_MODELS = listOf(
        "openrouter/free",
        "google/gemini-2.5-flash",
        "meta-llama/llama-3.1-8b-instruct:free",
        "qwen/qwen-2.5-72b-instruct:free",
        "microsoft/phi-3-medium-128k-instruct:free",
        "meta-llama/llama-3-8b-instruct:free"
    )

    private val roundRobinCursor = java.util.concurrent.atomic.AtomicInteger(0)
    private val keyCooldownUntilMs = java.util.concurrent.ConcurrentHashMap<String, Long>()

    fun parseKeyPool(keysCsv: String): List<String> {
        return keysCsv.split(Regex("[\\r\\n,;]+"))
            .map { it.trim() }
            .filter { it.isNotEmpty() }
            .distinct()
    }

    private fun getOrderedKeys(rawKeys: List<String>): List<String> {
        if (rawKeys.size <= 1) return rawKeys
        val count = rawKeys.size
        val start = (roundRobinCursor.getAndIncrement() and Int.MAX_VALUE) % count
        val rotated = (0 until count).map { idx -> rawKeys[(start + idx) % count] }
        val now = System.currentTimeMillis()
        val (healthy, cooling) = rotated.partition { key ->
            (keyCooldownUntilMs[key] ?: 0L) <= now
        }
        return healthy + cooling.sortedBy { keyCooldownUntilMs[it] ?: 0L }
    }

    suspend fun getChatCompletion(
        keysCsv: String,
        messages: JSONArray,
        model: String = "openrouter/free",
        onSuccess: (response: String) -> Unit,
        onFailure: (error: String) -> Unit
    ) {
        val rawKeys = parseKeyPool(keysCsv)

        if (rawKeys.isEmpty()) {
            onFailure("No OpenRouter API keys configured. Please add keys in Settings.")
            return
        }

        // We will try models in rotation: the requested model first, then fall back to other free models
        val modelsToTry = mutableListOf(model)
        FREE_MODELS.forEach { freeModel ->
            if (!modelsToTry.contains(freeModel)) {
                modelsToTry.add(freeModel)
            }
        }

        var lastError = "Unknown error"

        val formattedMessages = JSONArray()
        for (i in 0 until messages.length()) {
            val msg = messages.getJSONObject(i)
            val role = msg.optString("role", "user")
            val content = msg.optString("content", "")
            val imageBase64 = msg.optString("image_base64", "")
            val imageMimeType = msg.optString("image_mime_type", "image/jpeg").ifBlank { "image/jpeg" }

            if (imageBase64.isNotBlank() && imageMimeType.startsWith("image/")) {
                val contentArr = JSONArray().apply {
                    put(JSONObject().apply {
                        put("type", "text")
                        put("text", content.ifBlank { "Analyze this image and help me." })
                    })
                    put(JSONObject().apply {
                        put("type", "image_url")
                        put("image_url", JSONObject().apply {
                            put("url", "data:$imageMimeType;base64,$imageBase64")
                        })
                    })
                }
                formattedMessages.put(JSONObject().apply {
                    put("role", role)
                    put("content", contentArr)
                })
            } else {
                formattedMessages.put(JSONObject().apply {
                    put("role", role)
                    put("content", content)
                })
            }
        }

        val orderedKeys = getOrderedKeys(rawKeys)

        // Loop through pooled keys (in Round-Robin + healthy-first order) and models
        for (currentKey in orderedKeys) {
            for (currentModel in modelsToTry) {
                Log.d("OpenRouterClient", "Attempting request with model $currentModel")

                val payload = JSONObject().apply {
                    put("model", currentModel)
                    put("messages", formattedMessages)
                    put("temperature", 0.3)
                }

                val request = Request.Builder()
                    .url(OPENROUTER_URL)
                    .addHeader("Authorization", "Bearer $currentKey")
                    .addHeader("Content-Type", "application/json")
                    .addHeader("HTTP-Referer", "https://swapnopay.org")
                    .addHeader("X-Title", "SwapnoPay Smart Ledger")
                    .post(payload.toString().toRequestBody(JSON_MEDIA_TYPE))
                    .build()

                try {
                    var rotateKeyImmediately = false
                    val contentResult = withContext(Dispatchers.IO) {
                        client.newCall(request).execute().use { response ->
                            val bodyStr = response.body?.string()
                            val code = response.code
                            
                            if (response.isSuccessful && bodyStr != null) {
                                val json = JSONObject(bodyStr)
                                val choices = json.optJSONArray("choices")
                                if (choices != null && choices.length() > 0) {
                                    val choice = choices.getJSONObject(0)
                                    val messageObj = choice.optJSONObject("message")
                                    val content = messageObj?.optString("content", "") ?: ""
                                    if (content.isNotEmpty()) {
                                        keyCooldownUntilMs.remove(currentKey)
                                        return@use content
                                    }
                                }
                                lastError = "Response was successful but content was empty."
                            } else {
                                val errorDetail = bodyStr ?: "HTTP error $code"
                                lastError = "Model $currentModel failed: Code $code - $errorDetail"
                                Log.w("OpenRouterClient", "Call failed for model $currentModel: $errorDetail")
                                if (code == 429 || code == 401 || code == 402 || code == 403) {
                                    val cooldownMs = if (code == 429) 60_000L else 300_000L
                                    keyCooldownUntilMs[currentKey] = System.currentTimeMillis() + cooldownMs
                                    rotateKeyImmediately = true
                                }
                            }
                            null
                        }
                    }
                    if (contentResult != null) {
                        onSuccess(contentResult)
                        return
                    }
                    if (rotateKeyImmediately) {
                        break // Rotate to next OpenRouter key in the pool
                    }
                } catch (e: Exception) {
                    lastError = "Model $currentModel network exception: ${e.localizedMessage ?: "timeout"}"
                    Log.e("OpenRouterClient", "Exception during OpenRouter call for model $currentModel", e)
                }
            }
        }

        // If we reach here, all combinations failed
        onFailure("All API keys and free models failed. Last error: $lastError")
    }
}
