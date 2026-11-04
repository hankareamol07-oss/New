package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

public class AddStudentRequest {
    public String name;
    @SerializedName("name_mr")
    public String nameMr;
    @SerializedName("gr_no")
    public String grNo;
    @SerializedName("roll_no")
    public String rollNo;
    public String grade;
    public String section;
    public String gender;
    @SerializedName("date_of_birth")
    public String dateOfBirth;
    @SerializedName("father_name")
    public String fatherName;
    @SerializedName("mother_name")
    public String motherName;
    public String phone;
    @SerializedName("aadhar_no")
    public String aadharNo;
    @SerializedName("apaar_id")
    public String apaarId;
    public String caste;
    @SerializedName("blood_group")
    public String bloodGroup;

    public AddStudentRequest(String name, String nameMr, String grNo, String rollNo,
                             String grade, String section, String gender, String dateOfBirth,
                             String fatherName, String motherName, String phone,
                             String aadharNo, String apaarId, String caste, String bloodGroup) {
        this.name = name;
        this.nameMr = nameMr;
        this.grNo = grNo;
        this.rollNo = rollNo;
        this.grade = grade;
        this.section = section;
        this.gender = gender;
        this.dateOfBirth = dateOfBirth;
        this.fatherName = fatherName;
        this.motherName = motherName;
        this.phone = phone;
        this.aadharNo = aadharNo;
        this.apaarId = apaarId;
        this.caste = caste;
        this.bloodGroup = bloodGroup;
    }
}
