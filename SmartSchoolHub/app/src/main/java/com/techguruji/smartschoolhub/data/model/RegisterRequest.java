package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

public class RegisterRequest {
    @SerializedName("name")          private String name;
    @SerializedName("name_mr")       private String nameMr;
    @SerializedName("email")         private String email;
    @SerializedName("phone")         private String phone;
    @SerializedName("password")      private String password;
    @SerializedName("udise_code")    private String udiseCode;
    @SerializedName("district")      private String district;
    @SerializedName("taluka")        private String taluka;
    @SerializedName("village")       private String village;
    @SerializedName("pin_code")      private String pinCode;

    public RegisterRequest(String name, String nameMr, String email, String phone,
                           String password, String udiseCode, String district,
                           String taluka, String village, String pinCode) {
        this.name = name;
        this.nameMr = nameMr;
        this.email = email;
        this.phone = phone;
        this.password = password;
        this.udiseCode = udiseCode;
        this.district = district;
        this.taluka = taluka;
        this.village = village;
        this.pinCode = pinCode;
    }
}
