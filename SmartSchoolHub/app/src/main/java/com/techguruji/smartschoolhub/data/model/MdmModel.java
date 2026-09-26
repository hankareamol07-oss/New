package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;
import java.util.Map;

public class MdmModel {

    public static class MdmSummary {
        @SerializedName("total_beneficiaries")
        public int totalBeneficiaries;
        @SerializedName("total_rice_kg")
        public double totalRiceKg;
        @SerializedName("total_dal_kg")
        public double totalDalKg;
        @SerializedName("is_completed")
        public boolean isCompleted;
    }

    public static class MdmEntry {
        public int beneficiaries;
        @SerializedName("menu_id")
        public int menuId;
        @SerializedName("menu_name")
        public String menuName;
        public boolean cooked;
        public boolean holiday;
        @SerializedName("rice_kg")
        public double riceKg;
        @SerializedName("dal_kg")
        public double dalKg;
        public String remarks;
    }

    public static class MenuItem {
        public int id;
        public String name;
    }

    public static class MdmResponse {
        public boolean success;
        public String message;
        public String date;
        public MdmSummary summary;
        public Map<String, MdmEntry> entries;
        public List<MenuItem> menus;
    }

    public static class MdmGroupSaveItem {
        public int beneficiaries;
        @SerializedName("menu_id")
        public int menuId;
        @SerializedName("menu_name")
        public String menuName;
        public boolean cooked;
        public boolean holiday;
        public String remarks;

        public MdmGroupSaveItem(int beneficiaries, int menuId, String menuName, boolean cooked, boolean holiday, String remarks) {
            this.beneficiaries = beneficiaries;
            this.menuId = menuId;
            this.menuName = menuName;
            this.cooked = cooked;
            this.holiday = holiday;
            this.remarks = remarks;
        }
    }

    public static class MdmSaveRequest {
        public String date;
        @SerializedName("group_1_5")
        public MdmGroupSaveItem group1to5;
        @SerializedName("group_6_8")
        public MdmGroupSaveItem group6to8;

        public MdmSaveRequest(String date, MdmGroupSaveItem group1to5, MdmGroupSaveItem group6to8) {
            this.date = date;
            this.group1to5 = group1to5;
            this.group6to8 = group6to8;
        }
    }
}
