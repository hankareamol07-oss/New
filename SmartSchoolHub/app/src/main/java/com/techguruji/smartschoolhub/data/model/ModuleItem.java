package com.techguruji.smartschoolhub.data.model;

import androidx.annotation.DrawableRes;
import androidx.annotation.ColorRes;

/**
 * Module item — represents a dashboard module card.
 */
public class ModuleItem {

    private final String title;
    private final String titleEn;
    private final String description;
    private final String badge;
    private final String url;          // full web URL to open in WebView or Chrome Tab
    private final int iconRes;         // drawable resource ID
    private final int iconBgColorRes;  // color resource for icon background
    private final int iconColorRes;    // color resource for icon tint
    private final boolean isFree;      // whether module is accessible on free plan
    private final boolean isNew;       // show "नवीन" badge

    public ModuleItem(String title, String titleEn, String description, String badge,
                      String url, int iconRes, int iconBgColorRes, int iconColorRes,
                      boolean isFree, boolean isNew) {
        this.title = title;
        this.titleEn = titleEn;
        this.description = description;
        this.badge = badge;
        this.url = url;
        this.iconRes = iconRes;
        this.iconBgColorRes = iconBgColorRes;
        this.iconColorRes = iconColorRes;
        this.isFree = isFree;
        this.isNew = isNew;
    }

    public String getTitle() { return title; }
    public String getTitleEn() { return titleEn; }
    public String getDescription() { return description; }
    public String getBadge() { return badge; }
    public String getUrl() { return url; }
    public int getIconRes() { return iconRes; }
    public int getIconBgColorRes() { return iconBgColorRes; }
    public int getIconColorRes() { return iconColorRes; }
    public boolean isFree() { return isFree; }
    public boolean isNew() { return isNew; }
}
