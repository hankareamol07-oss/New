package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class AttendanceModel {

    public static class AttendanceStudent {
        public int id;
        public String name;
        @SerializedName("name_mr")
        public String nameMr;
        @SerializedName("roll_no")
        public String rollNo;
        public String grade;
        public String section;
        public String gender;
        public String status; // "P", "A", "L"

        public String getDisplayName() {
            if (nameMr != null && !nameMr.trim().isEmpty()) {
                return nameMr;
            }
            return name != null ? name : "";
        }
    }

    public static class AttendanceSummary {
        public int total;
        public int present;
        public int absent;
        public int leave;
    }

    public static class AttendanceResponse {
        public boolean success;
        public String message;
        public String date;
        public AttendanceSummary summary;
        public List<AttendanceStudent> students;
    }

    public static class StudentStatusItem {
        @SerializedName("student_id")
        public int studentId;
        public String status;

        public StudentStatusItem(int studentId, String status) {
            this.studentId = studentId;
            this.status = status;
        }
    }

    public static class AttendanceSaveRequest {
        public String date;
        public List<StudentStatusItem> attendance;

        public AttendanceSaveRequest(String date, List<StudentStatusItem> attendance) {
            this.date = date;
            this.attendance = attendance;
        }
    }
}
