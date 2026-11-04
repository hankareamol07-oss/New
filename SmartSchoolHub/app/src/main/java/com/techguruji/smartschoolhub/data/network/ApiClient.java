package com.techguruji.smartschoolhub.data.network;

import android.content.Context;

import com.techguruji.smartschoolhub.utils.SessionManager;

import java.io.IOException;
import java.util.concurrent.TimeUnit;

import okhttp3.Interceptor;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

/**
 * ApiClient — singleton Retrofit + OkHttp client.
 * Base URL: https://vijetaacademysangli.in/techguruji/api/
 */
public class ApiClient {

    private static final String BASE_URL = "https://vijetaacademysangli.in/techguruji/api/";
    private static final String EXAM_BASE_URL = "https://vijetaacademysangli.in/techguruji/exam_paper/";
    private static ApiClient instance;
    private final ApiService apiService;
    private final ExamApiService examApiService;
    private final NativeApi nativeApi;
    private static Context appContext;

    private ApiClient(Context context) {
        // Logging interceptor (debug only)
        HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
        logging.setLevel(HttpLoggingInterceptor.Level.BODY);

        // Auth + User-Agent interceptor — identifies app properly to avoid bot detection
        Interceptor authInterceptor = chain -> {
            Request original = chain.request();
            SessionManager session = SessionManager.getInstance(context);
            String token = session.getToken();

            // Use a real browser-like User-Agent so the website security
            // system does NOT flag this as a bot/scraper
            String userAgent = "Mozilla/5.0 (Linux; Android 11; SmartSchoolHub) "
                    + "AppleWebKit/537.36 (KHTML, like Gecko) "
                    + "Chrome/120.0.0.0 Mobile Safari/537.36 "
                    + "SmartSchoolHub-Android/1.0.0";

            Request.Builder builder = original.newBuilder()
                    .header("User-Agent", userAgent)
                    .header("Accept", "application/json")
                    .header("X-App-Source", "android")
                    .header("X-App-Version", "1.0.0");

            if (token != null && !token.isEmpty()) {
                builder.header("Authorization", "Bearer " + token);
            }
            return chain.proceed(builder.build());
        };

        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(authInterceptor)
                .addInterceptor(logging)
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .writeTimeout(30, TimeUnit.SECONDS)
                .build();

        Retrofit retrofit = new Retrofit.Builder()
                .baseUrl(BASE_URL)
                .client(client)
                .addConverterFactory(GsonConverterFactory.create())
                .build();

        apiService = retrofit.create(ApiService.class);
        nativeApi = retrofit.create(NativeApi.class);

        examApiService = new Retrofit.Builder()
                .baseUrl(EXAM_BASE_URL)
                .client(client)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
                .create(ExamApiService.class);
    }

    public static void init(Context context) {
        appContext = context.getApplicationContext();
        instance = new ApiClient(appContext);
    }

    public static ApiClient getInstance() {
        if (instance == null) {
            throw new IllegalStateException("ApiClient not initialized. Call ApiClient.init() first.");
        }
        return instance;
    }

    public ApiService getApiService() {
        return apiService;
    }

    public ExamApiService getExamApiService() {
        return examApiService;
    }

    public NativeApi getNativeApi() {
        return nativeApi;
    }
}
