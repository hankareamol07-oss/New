package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.Map;

public class StudentDetailResponse {
    public boolean success;
    public String message;
    public StudentListResponse.Student student;
    @SerializedName("attendance_summary")
    public Map<String, Integer> attendanceSummary;
}
