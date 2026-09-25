package com.techguruji.smartschoolhub.data.network;

import com.techguruji.smartschoolhub.data.model.ExamModel;

import java.util.List;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Query;

/** Exam Paper Generator endpoints (exam_paper/api.php?action=...). */
public interface ExamApiService {

    @GET("api.php?action=standards")
    Call<List<ExamModel.Standard>> getStandards();

    @GET("api.php?action=subjects")
    Call<List<ExamModel.Subject>> getSubjects(@Query("standard_id") int standardId);

    @GET("api.php?action=chapters")
    Call<List<ExamModel.Chapter>> getChapters(@Query("subject_id") int subjectId);

    @GET("api.php?action=question_types")
    Call<List<ExamModel.QuestionType>> getQuestionTypes(@Query("chapter_ids[]") List<Integer> chapterIds);

    @POST("api.php?action=random")
    Call<List<ExamModel.Question>> randomQuestions(@Body ExamModel.RandomRequest request);
}
