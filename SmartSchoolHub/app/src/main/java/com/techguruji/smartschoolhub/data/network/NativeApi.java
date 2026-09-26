package com.techguruji.smartschoolhub.data.network;

import com.google.gson.JsonObject;

import java.util.Map;

import okhttp3.ResponseBody;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.QueryMap;

/**
 * Bearer-token JSON endpoints that mirror the PHP module pages one-to-one
 * (api/cce_native.php, api/hpc_native.php, api/tachan_native.php, api/report.php).
 * Every response is {success, message, ...payload}.
 */
public interface NativeApi {

    @GET("cce_native.php")
    Call<JsonObject> cce(@QueryMap Map<String, String> params);

    @POST("cce_native.php")
    Call<JsonObject> ccePost(@Body JsonObject body);

    @GET("hpc_native.php")
    Call<JsonObject> hpc(@QueryMap Map<String, String> params);

    @POST("hpc_native.php")
    Call<JsonObject> hpcPost(@Body JsonObject body);

    @GET("tachan_native.php")
    Call<JsonObject> tachan(@QueryMap Map<String, String> params);

    @POST("tachan_native.php")
    Call<JsonObject> tachanPost(@Body JsonObject body);

    /** Renders one of the whitelisted PHP report/print pages as HTML for the print framework. */
    @GET("report.php")
    Call<ResponseBody> report(@QueryMap Map<String, String> params);
}
