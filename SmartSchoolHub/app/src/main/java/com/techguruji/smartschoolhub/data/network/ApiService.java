package com.techguruji.smartschoolhub.data.network;

import com.techguruji.smartschoolhub.data.model.AddStudentRequest;
import com.techguruji.smartschoolhub.data.model.ApiResponse;
import com.techguruji.smartschoolhub.data.model.AttendanceModel;
import com.techguruji.smartschoolhub.data.model.CceModel;
import com.techguruji.smartschoolhub.data.model.DashboardData;
import com.techguruji.smartschoolhub.data.model.FeeModel;
import com.techguruji.smartschoolhub.data.model.LoginRequest;
import com.techguruji.smartschoolhub.data.model.LoginResponse;
import com.techguruji.smartschoolhub.data.model.MdmModel;
import com.techguruji.smartschoolhub.data.model.ParipathData;
import com.techguruji.smartschoolhub.data.model.RegisterRequest;
import com.techguruji.smartschoolhub.data.model.StudentDetailResponse;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.model.TachanModel;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Query;

/**
 * Retrofit API interface — maps to PHP backend endpoints.
 * All endpoints are under: /techguruji/api/
 */
public interface ApiService {

    // ─── AUTH ────────────────────────────────────────────────────────────────

    /** POST /api/login.php — login with email/phone + password */
    @POST("login.php")
    Call<LoginResponse> login(@Body LoginRequest request);

    /** POST /api/register.php — school registration */
    @POST("register.php")
    Call<LoginResponse> register(@Body RegisterRequest request);

    /** POST /api/logout.php — invalidate session */
    @POST("logout.php")
    Call<ApiResponse> logout();

    // ─── DASHBOARD ───────────────────────────────────────────────────────────

    /** GET /api/dashboard.php — all stats + quick data */
    @GET("dashboard.php")
    Call<DashboardData> getDashboard();

    // ─── PARIPATH ────────────────────────────────────────────────────────────

    /** GET /api/paripath.php?date=YYYY-MM-DD */
    @GET("paripath.php")
    Call<ParipathData> getParipath(@Query("date") String date);

    // ─── STUDENTS ────────────────────────────────────────────────────────────

    /** GET /api/students.php?std=&section=&q= */
    @GET("students.php")
    Call<StudentListResponse> getStudents(
            @Query("std") String std,
            @Query("section") String section
    );

    /** GET /api/students.php?q=search_query */
    @GET("students.php")
    Call<StudentListResponse> searchStudents(@Query("q") String query);

    /** GET /api/students.php?id=X */
    @GET("students.php")
    Call<StudentDetailResponse> getStudentDetail(@Query("id") int studentId);

    /** POST /api/students.php — add new student */
    @POST("students.php")
    Call<ApiResponse> addStudent(@Body AddStudentRequest request);

    // ─── ATTENDANCE (हजेरी) ──────────────────────────────────────────────────

    /** GET /api/attendance.php?date=YYYY-MM-DD&grade=X&section=Y */
    @GET("attendance.php")
    Call<AttendanceModel.AttendanceResponse> getAttendance(
            @Query("date") String date,
            @Query("grade") String grade,
            @Query("section") String section
    );

    /** POST /api/attendance.php — save daily attendance */
    @POST("attendance.php")
    Call<ApiResponse> saveAttendance(@Body AttendanceModel.AttendanceSaveRequest request);

    // ─── TACHAN (दैनिक टाचण) ─────────────────────────────────────────────────

    /** GET /api/tachan.php?date=YYYY-MM-DD&grade=X */
    @GET("tachan.php")
    Call<TachanModel.TachanResponse> getTachan(
            @Query("date") String date,
            @Query("grade") String grade
    );

    /** POST /api/tachan.php — add new lesson plan */
    @POST("tachan.php")
    Call<ApiResponse> addTachan(@Body TachanModel.AddTachanRequest request);

    // ─── MDM (पोषण आहार) ─────────────────────────────────────────────────────

    /** GET /api/mdm.php?date=YYYY-MM-DD */
    @GET("mdm.php")
    Call<MdmModel.MdmResponse> getMdm(@Query("date") String date);

    /** POST /api/mdm.php — save daily MDM */
    @POST("mdm.php")
    Call<ApiResponse> saveMdm(@Body MdmModel.MdmSaveRequest request);

    // ─── FEE (फी व्यवस्थापन) ────────────────────────────────────────────────

    /** GET /api/fee.php?grade=X */
    @GET("fee.php")
    Call<FeeModel.FeeResponse> getFees(@Query("grade") String grade);

    /** POST /api/fee.php — collect fee */
    @POST("fee.php")
    Call<FeeModel.FeeCollectResponse> collectFee(@Body FeeModel.FeeCollectRequest request);

    // ─── CCE (मूल्यमापन) ─────────────────────────────────────────────────────

    /** GET /api/cce.php?grade=X&subject=Y&term=Z&tool=W */
    @GET("cce.php")
    Call<CceModel.CceResponse> getCceMarks(
            @Query("grade") String grade,
            @Query("subject") String subject,
            @Query("term") int term,
            @Query("tool") String tool
    );

    /** POST /api/cce.php — save marks */
    @POST("cce.php")
    Call<ApiResponse> saveCceMarks(@Body CceModel.CceSaveRequest request);

    // ─── SCHOOL PROFILE ──────────────────────────────────────────────────────

    /** GET /api/profile.php */
    @GET("profile.php")
    Call<DashboardData> getProfile();
}
