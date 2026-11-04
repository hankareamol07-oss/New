package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

public class LoginResponse {

    @SerializedName("success")
    private boolean success;

    @SerializedName("message")
    private String message;

    @SerializedName("token")
    private String token;

    @SerializedName("school")
    private SchoolData school;

    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public String getToken() { return token; }
    public SchoolData getSchool() { return school; }

    public static class SchoolData {
        @SerializedName("id")
        private int id;

        @SerializedName("name")
        private String name;

        @SerializedName("name_mr")
        private String nameMr;

        @SerializedName("udise_code")
        private String udiseCode;

        @SerializedName("email")
        private String email;

        @SerializedName("phone")
        private String phone;

        @SerializedName("plan_id")
        private int planId;

        @SerializedName("plan_name")
        private String planName;

        @SerializedName("is_subscribed")
        private boolean isSubscribed;

        @SerializedName("subscription_end")
        private String subscriptionEnd;

        @SerializedName("district")
        private String district;

        public int getId() { return id; }
        public String getName() { return name; }
        public String getNameMr() { return nameMr; }
        public String getDisplayName() {
            return (nameMr != null && !nameMr.isEmpty()) ? nameMr : name;
        }
        public String getUdiseCode() { return udiseCode; }
        public String getEmail() { return email; }
        public String getPhone() { return phone; }
        public int getPlanId() { return planId; }
        public String getPlanName() { return planName; }
        public boolean isSubscribed() { return isSubscribed; }
        public String getSubscriptionEnd() { return subscriptionEnd; }
        public String getDistrict() { return district; }
    }
}
