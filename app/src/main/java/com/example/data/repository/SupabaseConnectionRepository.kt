package com.example.data.repository

import android.util.Log
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.TimeUnit

object SupabaseConnectionRepository {

    data class OrganizationItem(val id: String, val name: String, val slug: String)
    data class ProjectItem(val id: String, val name: String, val organizationId: String, val region: String, val status: String)

    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .writeTimeout(20, TimeUnit.SECONDS)
        .build()

    private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()

    // 1. START OAUTH FLOW: Calls Edge Function oauth-start
    suspend fun startOAuthFlow(
        controlPlaneUrl: String,
        userId: String,
        onSuccess: (authorizeUrl: String) -> Unit,
        onFailure: (String) -> Unit
    ) {
        val cleanUrl = controlPlaneUrl.trimEnd('/')
        val endpoint = "$cleanUrl/functions/v1/oauth-start"

        val bodyJson = JSONObject().apply {
            put("user_id", userId)
            put("redirect_back", "swapnopay://supabase-connected")
        }.toString()

        val request = Request.Builder()
            .url(endpoint)
            .post(bodyJson.toRequestBody(JSON_MEDIA_TYPE))
            .build()

        try {
            client.newCall(request).execute().use { response ->
                val bodyStr = response.body?.string()
                if (response.isSuccessful && bodyStr != null) {
                    val jsonObj = JSONObject(bodyStr)
                    val authUrl = jsonObj.optString("authorize_url", "")
                    if (authUrl.isNotBlank()) {
                        onSuccess(authUrl)
                        return
                    }
                }
            }
        } catch (e: Exception) {
            Log.w("SupabaseConnRepo", "startOAuthFlow backend request error: ${e.message}")
        }

        // Direct Supabase OAuth 2.0 PKCE Authorize fallback URL
        val fallbackState = java.util.UUID.randomUUID().toString().replace("-", "")
        val directAuthorizeUrl = "https://api.supabase.com/v1/oauth/authorize?client_id=5d3dcd9b-1acf-4e31-96d2-d673af42a18b&redirect_uri=https://api.swapnopay.top/v1/oauth/callback&response_type=code&state=$fallbackState"
        onSuccess(directAuthorizeUrl)
    }

    // 2. FETCH ORGANIZATIONS & PROJECTS: Calls Edge Function projects
    suspend fun fetchOrganizationsAndProjects(
        controlPlaneUrl: String,
        userId: String,
        txId: String? = null,
        onSuccess: (orgs: List<OrganizationItem>, projects: List<ProjectItem>) -> Unit,
        onFailure: (String) -> Unit
    ) {
        val cleanUrl = controlPlaneUrl.trimEnd('/')
        val endpoint = "$cleanUrl/functions/v1/projects"

        val bodyJson = JSONObject().apply {
            put("user_id", userId)
            if (!txId.isNullOrBlank()) put("tx_id", txId)
        }.toString()

        val request = Request.Builder()
            .url(endpoint)
            .post(bodyJson.toRequestBody(JSON_MEDIA_TYPE))
            .build()

        try {
            client.newCall(request).execute().use { response ->
                val bodyStr = response.body?.string()
                if (response.isSuccessful && bodyStr != null) {
                    val json = JSONObject(bodyStr)
                    val orgsArray = json.optJSONArray("organizations") ?: JSONArray()
                    val projArray = json.optJSONArray("projects") ?: JSONArray()

                    val orgs = mutableListOf<OrganizationItem>()
                    for (i in 0 until orgsArray.length()) {
                        val obj = orgsArray.getJSONObject(i)
                        orgs.add(
                            OrganizationItem(
                                id = obj.optString("id", ""),
                                name = obj.optString("name", "Organization"),
                                slug = obj.optString("slug", obj.optString("id", ""))
                            )
                        )
                    }

                    val projs = mutableListOf<ProjectItem>()
                    for (i in 0 until projArray.length()) {
                        val obj = projArray.getJSONObject(i)
                        projs.add(
                            ProjectItem(
                                id = obj.optString("id", ""),
                                name = obj.optString("name", "Project"),
                                organizationId = obj.optString("organization_id", ""),
                                region = obj.optString("region", ""),
                                status = obj.optString("status", "")
                            )
                        )
                    }

                    onSuccess(orgs, projs)
                } else {
                    onFailure("Failed to load projects (${response.code})")
                }
            }
        } catch (e: Exception) {
            Log.e("SupabaseConnRepo", "fetchOrganizationsAndProjects Exception", e)
            onFailure(e.localizedMessage ?: "Network connection failed.")
        }
    }

    // 3. CREATE PROJECT: Calls Edge Function provision (action: CREATE_PROJECT)
    suspend fun createProject(
        controlPlaneUrl: String,
        userId: String,
        orgSlug: String,
        projectName: String,
        dbPassword: String = java.util.UUID.randomUUID().toString().replace("-", "").take(16) + "Aa1!",
        txId: String? = null,
        onSuccess: (projectRef: String) -> Unit,
        onFailure: (String) -> Unit
    ) {
        val cleanUrl = controlPlaneUrl.trimEnd('/')
        val endpoint = "$cleanUrl/functions/v1/provision"

        val bodyJson = JSONObject().apply {
            put("user_id", userId)
            if (!txId.isNullOrBlank()) put("tx_id", txId)
            put("action", "CREATE_PROJECT")
            put("organization_slug", orgSlug)
            put("project_name", projectName)
            put("db_password", dbPassword)
        }.toString()

        val request = Request.Builder()
            .url(endpoint)
            .post(bodyJson.toRequestBody(JSON_MEDIA_TYPE))
            .build()

        try {
            client.newCall(request).execute().use { response ->
                val bodyStr = response.body?.string()
                if (response.isSuccessful && bodyStr != null) {
                    val json = JSONObject(bodyStr)
                    val ref = json.optString("project_ref", "")
                    if (ref.isNotBlank()) {
                        onSuccess(ref)
                    } else {
                        onFailure(json.optString("error", "Project creation returned empty reference"))
                    }
                } else {
                    onFailure("Project Creation Error (${response.code})")
                }
            }
        } catch (e: Exception) {
            Log.e("SupabaseConnRepo", "createProject Exception", e)
            onFailure(e.localizedMessage ?: "Failed to trigger project creation.")
        }
    }

    // 4. CHECK PROJECT HEALTH: Calls Edge Function provision (action: CHECK_HEALTH)
    suspend fun checkProjectHealth(
        controlPlaneUrl: String,
        userId: String,
        projectRef: String,
        txId: String? = null,
        onSuccess: (isHealthy: Boolean) -> Unit,
        onFailure: (String) -> Unit
    ) {
        val cleanUrl = controlPlaneUrl.trimEnd('/')
        val endpoint = "$cleanUrl/functions/v1/provision"

        val bodyJson = JSONObject().apply {
            put("user_id", userId)
            if (!txId.isNullOrBlank()) put("tx_id", txId)
            put("action", "CHECK_HEALTH")
            put("project_ref", projectRef)
        }.toString()

        val request = Request.Builder()
            .url(endpoint)
            .post(bodyJson.toRequestBody(JSON_MEDIA_TYPE))
            .build()

        try {
            client.newCall(request).execute().use { response ->
                val bodyStr = response.body?.string()
                if (response.isSuccessful && bodyStr != null) {
                    val json = JSONObject(bodyStr)
                    val status = json.optString("status", "")
                    onSuccess(status == "ACTIVE_HEALTHY")
                } else {
                    onFailure("Health Check Error (${response.code})")
                }
            }
        } catch (e: Exception) {
            onFailure(e.localizedMessage ?: "Health check network error.")
        }
    }

    // 5. APPLY SCHEMA & FINALIZE: Calls Edge Function provision (action: APPLY_SCHEMA_AND_FINALIZE)
    suspend fun applySchemaAndFinalize(
        controlPlaneUrl: String,
        userId: String,
        projectRef: String,
        txId: String? = null,
        onSuccess: (projectUrl: String, publishableKey: String) -> Unit,
        onFailure: (String) -> Unit
    ) {
        val cleanUrl = controlPlaneUrl.trimEnd('/')
        val endpoint = "$cleanUrl/functions/v1/provision"

        val bodyJson = JSONObject().apply {
            put("user_id", userId)
            if (!txId.isNullOrBlank()) put("tx_id", txId)
            put("action", "APPLY_SCHEMA_AND_FINALIZE")
            put("project_ref", projectRef)
        }.toString()

        val request = Request.Builder()
            .url(endpoint)
            .post(bodyJson.toRequestBody(JSON_MEDIA_TYPE))
            .build()

        try {
            client.newCall(request).execute().use { response ->
                val bodyStr = response.body?.string()
                if (response.isSuccessful && bodyStr != null) {
                    val json = JSONObject(bodyStr)
                    var projectUrl = json.optString("project_url", "")
                    if (projectUrl.isBlank() && projectRef.isNotBlank()) {
                        projectUrl = "https://${projectRef.trim()}.supabase.co"
                    }
                    var pubKey = json.optString("publishable_key", "")
                    if (pubKey.isBlank()) pubKey = json.optString("anon_key", "")
                    if (pubKey.isBlank()) pubKey = json.optString("key", "")

                    if (projectUrl.isNotBlank() && pubKey.isNotBlank()) {
                        onSuccess(projectUrl, pubKey)
                    } else if (projectUrl.isNotBlank()) {
                        onSuccess(projectUrl, pubKey)
                    } else {
                        onFailure(json.optString("error", "Failed to retrieve project credentials"))
                    }
                } else {
                    onFailure("Provisioning Error (${response.code})")
                }
            }
        } catch (e: Exception) {
            Log.e("SupabaseConnRepo", "applySchemaAndFinalize Exception", e)
            onFailure(e.localizedMessage ?: "Provisioning execution failed.")
        }
    }
}
