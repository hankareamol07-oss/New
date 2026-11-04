package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class FeeModel {

    public static class FeeSummary {
        @SerializedName("total_expected")
        public double totalExpected;
        @SerializedName("total_collected")
        public double totalCollected;
        @SerializedName("total_pending")
        public double totalPending;
        @SerializedName("total_students")
        public int totalStudents;
    }

    public static class FeeStudent {
        public int id;
        public String name;
        @SerializedName("name_mr")
        public String nameMr;
        @SerializedName("roll_no")
        public String rollNo;
        public String grade;
        public String section;
        @SerializedName("paid_amount")
        public double paidAmount;
        @SerializedName("total_fee")
        public double totalFee;
        @SerializedName("pending_amount")
        public double pendingAmount;
        public String status; // "paid", "partial", "unpaid"

        public String getDisplayName() {
            if (nameMr != null && !nameMr.trim().isEmpty()) {
                return nameMr;
            }
            return name != null ? name : "";
        }
    }

    public static class FeeResponse {
        public boolean success;
        public String message;
        public FeeSummary summary;
        public List<FeeStudent> students;
    }

    public static class FeeCollectRequest {
        @SerializedName("student_id")
        public int studentId;
        public double amount;
        public String mode;
        @SerializedName("fee_type")
        public String feeType;
        @SerializedName("paid_on")
        public String paidOn;
        public String remarks;

        public FeeCollectRequest(int studentId, double amount, String mode, String feeType, String paidOn, String remarks) {
            this.studentId = studentId;
            this.amount = amount;
            this.mode = mode;
            this.feeType = feeType;
            this.paidOn = paidOn;
            this.remarks = remarks;
        }
    }

    public static class FeeCollectResponse {
        public boolean success;
        public String message;
        @SerializedName("receipt_no")
        public String receiptNo;
        public double amount;
        @SerializedName("paid_on")
        public String paidOn;
        public String mode;
    }
}
