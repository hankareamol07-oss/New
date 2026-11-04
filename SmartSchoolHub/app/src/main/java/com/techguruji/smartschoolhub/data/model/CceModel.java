package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class CceModel {

    public static class CceStudent {
        public int id;
        public String name;
        @SerializedName("name_mr")
        public String nameMr;
        @SerializedName("roll_no")
        public String rollNo;
        public String grade;
        public String section;
        public double marks;
        @SerializedName("max_marks")
        public double maxMarks;
        @SerializedName("grade_point")
        public String gradePoint;

        public String getDisplayName() {
            if (nameMr != null && !nameMr.trim().isEmpty()) {
                return nameMr;
            }
            return name != null ? name : "";
        }
    }

    public static class CceResponse {
        public boolean success;
        public String message;
        public String grade;
        public String subject;
        public int term;
        public String tool;
        public List<String> subjects;
        public List<String> tools;
        public List<CceStudent> students;
    }

    public static class StudentMarkItem {
        @SerializedName("student_id")
        public int studentId;
        public double marks;

        public StudentMarkItem(int studentId, double marks) {
            this.studentId = studentId;
            this.marks = marks;
        }
    }

    public static class CceSaveRequest {
        public String grade;
        public String subject;
        public int term;
        public String tool;
        @SerializedName("max_marks")
        public double maxMarks;
        public List<StudentMarkItem> marks;

        public CceSaveRequest(String grade, String subject, int term, String tool, double maxMarks, List<StudentMarkItem> marks) {
            this.grade = grade;
            this.subject = subject;
            this.term = term;
            this.tool = tool;
            this.maxMarks = maxMarks;
            this.marks = marks;
        }
    }
}
