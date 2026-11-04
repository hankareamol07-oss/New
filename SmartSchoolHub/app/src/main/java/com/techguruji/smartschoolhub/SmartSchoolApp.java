package com.techguruji.smartschoolhub;

import android.app.Application;
import android.content.Context;

import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.utils.SessionManager;

/**
 * Application class — initializes singletons on app start.
 */
public class SmartSchoolApp extends Application {

    private static SmartSchoolApp instance;

    @Override
    public void onCreate() {
        super.onCreate();
        instance = this;
        // Initialize ApiClient with app context
        ApiClient.init(this);
    }

    public static SmartSchoolApp getInstance() {
        return instance;
    }

    public static Context getAppContext() {
        return instance.getApplicationContext();
    }
}
