package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

import java.util.List;

/** Subscription plans (GET plans.php). */
public class PlanModel {

    public static class PlansResponse {
        public boolean success;
        public String message;
        public List<Plan> plans;
        @SerializedName("current_plan_id") public int currentPlanId;
        @SerializedName("subscription_end") public String subscriptionEnd;
        @SerializedName("checkout_url") public String checkoutUrl;
    }

    public static class Plan {
        public int id;
        public String name;
        @SerializedName("name_mr") public String nameMr;
        @SerializedName("max_students") public int maxStudents;
        public double price;
        @SerializedName("duration_months") public int durationMonths;
        public List<String> features;
        public List<String> modules;
        @SerializedName("is_current") public boolean isCurrent;

        public boolean isFree() { return price <= 0; }
        public boolean isUnlimited() { return maxStudents >= 9999; }
    }
}
