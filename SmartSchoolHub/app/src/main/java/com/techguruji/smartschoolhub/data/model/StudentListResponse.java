package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class StudentListResponse {

    @SerializedName("success")
    private boolean success;

    @SerializedName("students")
    private List<Student> students;

    @SerializedName("total")
    private int total;

    public boolean isSuccess() { return success; }
    public List<Student> getStudents() { return students; }
    public int getTotal() { return total; }

    public static class Student {
        @SerializedName("id")             private int id;
        @SerializedName("name")           private String name;
        @SerializedName("name_mr")        private String nameMr;
        @SerializedName("roll_no")        private String rollNo;
        @SerializedName("grade")          private String grade;
        @SerializedName("section")        private String section;
        @SerializedName("gender")         private String gender;
        @SerializedName("date_of_birth")  private String dateOfBirth;
        @SerializedName("photo")          private String photo;
        @SerializedName("father_name")    private String fatherName;
        @SerializedName("mother_name")    private String motherName;
        @SerializedName("phone")          private String phone;
        @SerializedName("apaar_id")       private String apaarId;
        @SerializedName("blood_group")    private String bloodGroup;
        @SerializedName("gr_no")          private String grNo;
        @SerializedName("aadhar_no")      private String aadharNo;
        @SerializedName("caste")          private String caste;
        @SerializedName("status")         private String status;

        public int getId() { return id; }
        public String getName() { return name; }
        public String getNameMr() { return nameMr; }
        public String getDisplayName() {
            return (nameMr != null && !nameMr.isEmpty()) ? nameMr : name;
        }
        public String getRollNo() { return rollNo; }
        public String getGrade() { return grade; }
        public String getSection() { return section; }
        public String getGender() { return gender; }
        public String getDateOfBirth() { return dateOfBirth; }
        public String getPhoto() { return photo; }
        public String getFatherName() { return fatherName; }
        public String getMotherName() { return motherName; }
        public String getPhone() { return phone; }
        public String getApaarId() { return apaarId; }
        public String getBloodGroup() { return bloodGroup; }
        public String getGrNo() { return grNo; }
        public String getAadharNo() { return aadharNo; }
        public String getCaste() { return caste; }
        public String getStatus() { return status; }
    }
}
