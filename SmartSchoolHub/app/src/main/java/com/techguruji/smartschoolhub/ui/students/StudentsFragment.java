package com.techguruji.smartschoolhub.ui.students;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.adapter.StudentAdapter;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.FragmentStudentsBinding;
import com.techguruji.smartschoolhub.ui.modules.ModuleWebActivity;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * StudentsFragment — shows list of all students with search, filter, and PDF printing.
 */
public class StudentsFragment extends Fragment {

    private FragmentStudentsBinding binding;
    private StudentAdapter adapter;
    private List<StudentListResponse.Student> allStudents = new ArrayList<>();

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentStudentsBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        setupToolbar();
        setupRecyclerView();
        setupSearch();
        setupFab();
        loadStudents();

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::loadStudents);
    }

    private void setupToolbar() {
        binding.toolbar.inflateMenu(R.menu.menu_web);
        binding.toolbar.setOnMenuItemClickListener(item -> {
            if (item.getItemId() == R.id.btnPrint) {
                if (allStudents != null && !allStudents.isEmpty()) {
                    SessionManager session = SessionManager.getInstance(requireContext());
                    String html = PdfPrintHelper.generateStudentListHtml(session, allStudents);
                    PdfPrintHelper.printHtml(requireActivity(), html, "Student_List");
                } else {
                    Toast.makeText(requireContext(), "छापण्यासाठी विद्यार्थी माहिती नाही.", Toast.LENGTH_SHORT).show();
                }
                return true;
            }
            return false;
        });
    }

    private void setupRecyclerView() {
        adapter = new StudentAdapter(new ArrayList<>(), student -> {
            // Open student module in WebView
            Intent intent = new Intent(requireContext(), ModuleWebActivity.class);
            intent.putExtra(ModuleWebActivity.EXTRA_TITLE, "विद्यार्थी माहिती");
            intent.putExtra(ModuleWebActivity.EXTRA_URL,
                    "https://vijetaacademysangli.in/techguruji/students/edit.php?id=" + student.getId());
            startActivity(intent);
        });
        binding.rvStudents.setLayoutManager(new LinearLayoutManager(requireContext()));
        binding.rvStudents.setAdapter(adapter);
        binding.rvStudents.setHasFixedSize(true);
    }

    private void setupSearch() {
        binding.etSearch.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            @Override public void afterTextChanged(Editable s) {}
            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                filterStudents(s.toString().trim());
            }
        });
    }

    private void setupFab() {
        binding.fabAddStudent.setOnClickListener(v -> {
            Intent intent = new Intent(requireContext(), ModuleWebActivity.class);
            intent.putExtra(ModuleWebActivity.EXTRA_TITLE, "नवीन विद्यार्थी नोंदणी");
            intent.putExtra(ModuleWebActivity.EXTRA_URL,
                    "https://vijetaacademysangli.in/techguruji/students/add.php");
            startActivity(intent);
        });
    }

    private void loadStudents() {
        showLoading(true);
        ApiClient.getInstance().getApiService().getStudents("", "")
                .enqueue(new Callback<StudentListResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<StudentListResponse> call,
                                           @NonNull Response<StudentListResponse> response) {
                        showLoading(false);
                        binding.swipeRefresh.setRefreshing(false);
                        if (response.isSuccessful() && response.body() != null
                                && response.body().isSuccess()) {
                            allStudents = response.body().getStudents();
                            adapter.updateList(allStudents);
                            binding.tvStudentCount.setText("एकूण: " + allStudents.size() + " विद्यार्थी");
                            binding.layoutEmpty.setVisibility(
                                    allStudents.isEmpty() ? View.VISIBLE : View.GONE);
                        } else {
                            showError();
                        }
                    }
                    @Override
                    public void onFailure(@NonNull Call<StudentListResponse> call,
                                         @NonNull Throwable t) {
                        showLoading(false);
                        binding.swipeRefresh.setRefreshing(false);
                        showError();
                    }
                });
    }

    private void filterStudents(String query) {
        if (query.isEmpty()) {
            adapter.updateList(allStudents);
            return;
        }
        List<StudentListResponse.Student> filtered = new ArrayList<>();
        for (StudentListResponse.Student s : allStudents) {
            String name = s.getDisplayName().toLowerCase();
            String roll = s.getRollNo() != null ? s.getRollNo() : "";
            if (name.contains(query.toLowerCase()) || roll.contains(query)) {
                filtered.add(s);
            }
        }
        adapter.updateList(filtered);
    }

    private void showLoading(boolean loading) {
        binding.shimmerLayout.setVisibility(loading ? View.VISIBLE : View.GONE);
        binding.rvStudents.setVisibility(loading ? View.GONE : View.VISIBLE);
        if (loading) binding.shimmerLayout.startShimmer();
        else binding.shimmerLayout.stopShimmer();
    }

    private void showError() {
        Snackbar.make(
                        binding.getRoot(), getString(R.string.no_internet),
                        Snackbar.LENGTH_LONG)
                .setAction(R.string.retry, v -> loadStudents()).show();
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
