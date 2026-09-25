package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class TachanModel {

    public static class TachanItem {
        public int id;
        public String date;
        public String grade;
        public int period;
        public String subject;
        public String topic;
        @SerializedName("learning_outcome")
        public String learningOutcome;
        public String materials;
        @SerializedName("eval_tool")
        public String evalTool;
        public String homework;
        public String activities;
    }

    public static class TachanResponse {
        public boolean success;
        public String message;
        public String date;
        public List<TachanItem> plans;
        public List<String> subjects;
    }

    public static class AddTachanRequest {
        public String date;
        public String grade;
        public int period;
        public String subject;
        public String topic;
        @SerializedName("learning_outcome")
        public String learningOutcome;
        public String materials;
        @SerializedName("eval_tool")
        public String evalTool;
        public String homework;
        public String activities;

        public AddTachanRequest(String date, String grade, int period, String subject,
                                String topic, String learningOutcome, String materials,
                                String evalTool, String homework, String activities) {
            this.date = date;
            this.grade = grade;
            this.period = period;
            this.subject = subject;
            this.topic = topic;
            this.learningOutcome = learningOutcome;
            this.materials = materials;
            this.evalTool = evalTool;
            this.homework = homework;
            this.activities = activities;
        }
    }
}
