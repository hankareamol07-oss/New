package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

public class DashboardData {

    @SerializedName("success")
    private boolean success;

    @SerializedName("school_name")
    private String schoolName;

    @SerializedName("school_name_mr")
    private String schoolNameMr;

    @SerializedName("udise_code")
    private String udiseCode;

    @SerializedName("academic_year")
    private String academicYear;

    @SerializedName("student_count")
    private int studentCount;

    @SerializedName("hpc_count")
    private int hpcCount;

    @SerializedName("hpc_completed")
    private int hpcCompleted;

    @SerializedName("teacher_count")
    private int teacherCount;

    @SerializedName("classes_count")
    private int classesCount;

    @SerializedName("cce_evaluated_count")
    private int cceEvaluatedCount;

    @SerializedName("is_subscribed")
    private boolean isSubscribed;

    @SerializedName("plan_name")
    private String planName;

    @SerializedName("plan_id")
    private int planId;

    @SerializedName("today_marathi")
    private String todayMarathi;

    @SerializedName("panchang_var")
    private String panchangVar;

    @SerializedName("suvichar")
    private String suvichar;

    @SerializedName("suvichar_source")
    private String suvicharSource;

    // Getters
    public boolean isSuccess() { return success; }
    public String getSchoolName() { return schoolName; }
    public String getSchoolNameMr() { return schoolNameMr; }
    public String getDisplaySchoolName() {
        return (schoolNameMr != null && !schoolNameMr.isEmpty()) ? schoolNameMr : schoolName;
    }
    public String getUdiseCode() { return udiseCode; }
    public String getAcademicYear() { return academicYear; }
    public int getStudentCount() { return studentCount; }
    public int getHpcCount() { return hpcCount; }
    public int getHpcCompleted() { return hpcCompleted; }
    public int getTeacherCount() { return teacherCount; }
    public int getClassesCount() { return classesCount; }
    public int getCceEvaluatedCount() { return cceEvaluatedCount; }
    public boolean isSubscribed() { return isSubscribed; }
    public String getPlanName() { return planName; }
    public int getPlanId() { return planId; }
    public String getTodayMarathi() { return todayMarathi; }
    public String getPanchangVar() { return panchangVar; }
    public String getSuvichar() { return suvichar; }
    public String getSuvicharSource() { return suvicharSource; }
}
