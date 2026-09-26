package com.techguruji.smartschoolhub.data.model;

/**
 * Module item — represents a dashboard module card.
 */
public class ModuleItem {

    public static final String KEY_PARIPATH = "paripath";
    public static final String KEY_HPC = "hpc";
    public static final String KEY_CCE = "cce";
    public static final String KEY_TACHAN = "tachan";
    public static final String KEY_HAJERI = "hajeri";
    public static final String KEY_MDM = "mdm";
    public static final String KEY_FEE = "fee";
    public static final String KEY_EXAM = "exam";
    public static final String KEY_STUDENTS = "students";
    public static final String KEY_CLASSES = "classes";
    public static final String KEY_BONAFIDE = "bonafide";
    public static final String KEY_GR = "gr";

    private final String key;
    private final String title;
    private final String titleEn;
    private final String description;
    private final String badge;
    private final int iconRes;         // drawable resource ID
    private final int iconBgColorRes;  // color resource for icon background
    private final int iconColorRes;    // color resource for icon tint
    private final boolean isFree;      // whether module is accessible on free plan
    private final boolean isNew;       // show "नवीन" badge

    public ModuleItem(String key, String title, String titleEn, String description, String badge,
                      int iconRes, int iconBgColorRes, int iconColorRes,
                      boolean isFree, boolean isNew) {
        this.key = key;
        this.title = title;
        this.titleEn = titleEn;
        this.description = description;
        this.badge = badge;
        this.iconRes = iconRes;
        this.iconBgColorRes = iconBgColorRes;
        this.iconColorRes = iconColorRes;
        this.isFree = isFree;
        this.isNew = isNew;
    }

    public String getKey() { return key; }
    public String getTitle() { return title; }
    public String getTitleEn() { return titleEn; }
    public String getDescription() { return description; }
    public String getBadge() { return badge; }
    public int getIconRes() { return iconRes; }
    public int getIconBgColorRes() { return iconBgColorRes; }
    public int getIconColorRes() { return iconColorRes; }
    public boolean isFree() { return isFree; }
    public boolean isNew() { return isNew; }
}
