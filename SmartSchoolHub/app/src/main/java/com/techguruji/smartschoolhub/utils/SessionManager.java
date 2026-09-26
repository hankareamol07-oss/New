package com.techguruji.smartschoolhub.utils;

import android.content.Context;
import android.content.SharedPreferences;

/**
 * SessionManager — stores login token, school info in SharedPreferences.
 */
public class SessionManager {

    private static final String PREF_NAME = "SmartSchoolSession";
    private static final String KEY_TOKEN = "auth_token";
    private static final String KEY_SCHOOL_ID = "school_id";
    private static final String KEY_SCHOOL_NAME = "school_name";
    private static final String KEY_SCHOOL_NAME_MR = "school_name_mr";
    private static final String KEY_UDISE = "udise_code";
    private static final String KEY_EMAIL = "email";
    private static final String KEY_PHONE = "phone";
    private static final String KEY_PLAN_ID = "plan_id";
    private static final String KEY_PLAN_NAME = "plan_name";
    private static final String KEY_IS_SUBSCRIBED = "is_subscribed";
    private static final String KEY_SUBSCRIPTION_END = "subscription_end";
    private static final String KEY_TEACHER_NAME = "teacher_name";
    private static final String KEY_DISTRICT = "district";
    private static final String KEY_LOGGED_IN = "is_logged_in";

    private static SessionManager instance;
    private final SharedPreferences prefs;
    private final SharedPreferences.Editor editor;

    private SessionManager(Context context) {
        prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        editor = prefs.edit();
    }

    public static synchronized SessionManager getInstance(Context context) {
        if (instance == null) {
            instance = new SessionManager(context.getApplicationContext());
        }
        return instance;
    }

    public void saveSession(String token, int schoolId, String schoolName, String schoolNameMr,
                            String udise, String email, String phone, int planId,
                            String planName, boolean isSubscribed, String subscriptionEnd,
                            String teacherName, String district) {
        editor.putBoolean(KEY_LOGGED_IN, true);
        editor.putString(KEY_TOKEN, token);
        editor.putInt(KEY_SCHOOL_ID, schoolId);
        editor.putString(KEY_SCHOOL_NAME, schoolName);
        editor.putString(KEY_SCHOOL_NAME_MR, schoolNameMr);
        editor.putString(KEY_UDISE, udise);
        editor.putString(KEY_EMAIL, email);
        editor.putString(KEY_PHONE, phone);
        editor.putInt(KEY_PLAN_ID, planId);
        editor.putString(KEY_PLAN_NAME, planName);
        editor.putBoolean(KEY_IS_SUBSCRIBED, isSubscribed);
        editor.putString(KEY_SUBSCRIPTION_END, subscriptionEnd);
        editor.putString(KEY_TEACHER_NAME, teacherName);
        editor.putString(KEY_DISTRICT, district);
        editor.apply();
    }

    public boolean isLoggedIn() {
        return prefs.getBoolean(KEY_LOGGED_IN, false);
    }

    public String getToken() { return prefs.getString(KEY_TOKEN, ""); }
    public int getSchoolId() { return prefs.getInt(KEY_SCHOOL_ID, -1); }
    public String getSchoolName() { return prefs.getString(KEY_SCHOOL_NAME, ""); }
    public String getSchoolNameMr() { return prefs.getString(KEY_SCHOOL_NAME_MR, ""); }
    public String getDisplaySchoolName() {
        String mr = getSchoolNameMr();
        return (mr != null && !mr.isEmpty()) ? mr : getSchoolName();
    }
    public String getUdise() { return prefs.getString(KEY_UDISE, ""); }
    public String getEmail() { return prefs.getString(KEY_EMAIL, ""); }
    public String getPhone() { return prefs.getString(KEY_PHONE, ""); }
    public int getPlanId() { return prefs.getInt(KEY_PLAN_ID, 1); }
    public String getPlanName() { return prefs.getString(KEY_PLAN_NAME, "मोफत"); }
    public boolean isSubscribed() { return prefs.getBoolean(KEY_IS_SUBSCRIBED, false); }
    public String getSubscriptionEnd() { return prefs.getString(KEY_SUBSCRIPTION_END, ""); }
    public String getTeacherName() { return prefs.getString(KEY_TEACHER_NAME, "शिक्षक"); }
    public String getDistrict() { return prefs.getString(KEY_DISTRICT, ""); }

    public void clearSession() {
        editor.clear();
        editor.apply();
    }
}
