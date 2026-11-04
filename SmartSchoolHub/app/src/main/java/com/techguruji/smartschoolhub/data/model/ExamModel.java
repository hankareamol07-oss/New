package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

import java.util.List;

/** Models for the Exam Paper Generator API (exam_paper/api.php). */
public class ExamModel {

    public static class Standard {
        @SerializedName("standard_id") public int id;
        public String name;
        public String medium;
        public String board;

        public String label() {
            return name + (medium == null || medium.isEmpty() ? "" : " (" + medium + ")");
        }
    }

    public static class Subject {
        @SerializedName("subject_id") public int id;
        public String name;
        @SerializedName("question_count") public int questionCount;
    }

    public static class Chapter {
        @SerializedName("chapter_id") public int id;
        public String name;
        @SerializedName("question_count") public int questionCount;
    }

    public static class QuestionType {
        @SerializedName("question_type") public String type;
        @SerializedName("question_number") public String qno;
        public int marks;
        public int total;

        public String typeLabel() {
            if (type == null) return "";
            switch (type) {
                case "mcq": return "बहुपर्यायी (MCQ)";
                case "fillinblanks": return "रिकाम्या जागा";
                default: return "वर्णनात्मक";
            }
        }
    }

    public static class Question {
        @SerializedName("question_id") public int id;
        @SerializedName("chapter_id") public int chapterId;
        @SerializedName("question_type") public String type;
        @SerializedName("question_number") public String qno;
        public int marks;
        public String passage;
        public String markup;
        @SerializedName("answer_markup") public String answerMarkup;
        @SerializedName("image_url") public String imageUrl;
    }

    public static class RandomRequest {
        @SerializedName("chapter_ids") public List<Integer> chapterIds;
        public String type;
        public String qno;
        public int count;
        public List<Integer> exclude;

        public RandomRequest(List<Integer> chapterIds, String type, String qno, int count, List<Integer> exclude) {
            this.chapterIds = chapterIds;
            this.type = type;
            this.qno = qno;
            this.count = count;
            this.exclude = exclude;
        }
    }

    /** One section of a generated paper: a question type/number + the picked questions. */
    public static class Section {
        public QuestionType type;
        public int count;
        public List<Question> questions;

        public Section(QuestionType type, int count) {
            this.type = type;
            this.count = count;
        }

        public int marks() {
            return questions == null ? 0 : questions.size() * type.marks;
        }
    }
}
