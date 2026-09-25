package com.techguruji.smartschoolhub.ui.modules;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.GridLayoutManager;

import androidx.navigation.Navigation;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.adapter.ModuleAdapter;
import com.techguruji.smartschoolhub.data.model.ModuleItem;
import com.techguruji.smartschoolhub.databinding.FragmentModulesBinding;
import com.techguruji.smartschoolhub.ui.hajeri.HajeriActivity;
import com.techguruji.smartschoolhub.ui.tachan.TachanActivity;

import java.util.ArrayList;
import java.util.List;

/**
 * ModulesFragment — full grid of all 12 school modules.
 * Directs core features to native Android screens and web modules to ModuleWebActivity.
 */
public class ModulesFragment extends Fragment {

    private FragmentModulesBinding binding;
    private static final String BASE = "https://vijetaacademysangli.in/techguruji/";

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentModulesBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);
        setupRecyclerView();
    }

    private void setupRecyclerView() {
        List<ModuleItem> modules = buildModuleList();

        ModuleAdapter adapter = new ModuleAdapter(modules, item -> {
            String title = item.getTitle();
            if (title.contains("हजेरी")) {
                Intent intent = new Intent(requireContext(), HajeriActivity.class);
                startActivity(intent);
                requireActivity().overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
            } else if (title.contains("टाचण")) {
                Intent intent = new Intent(requireContext(), TachanActivity.class);
                startActivity(intent);
                requireActivity().overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
            } else if (title.contains("HPC")) {
                try {
                    Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                            .navigate(R.id.hpcFragment);
                } catch (Exception e) {
                    openWebModule(title, item.getUrl());
                }
            } else if (title.contains("CCE")) {
                try {
                    Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                            .navigate(R.id.cceFragment);
                } catch (Exception e) {
                    openWebModule(title, item.getUrl());
                }
            } else if (title.contains("परिपाठ")) {
                try {
                    Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                            .navigate(R.id.paripathFragment);
                } catch (Exception e) {
                    openWebModule(title, item.getUrl());
                }
            } else if (title.contains("विद्यार्थी")) {
                try {
                    Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                            .navigate(R.id.studentsFragment);
                } catch (Exception e) {
                    openWebModule(title, item.getUrl());
                }
            } else {
                openWebModule(title, item.getUrl());
            }
        });

        binding.rvModules.setLayoutManager(new GridLayoutManager(requireContext(), 2));
        binding.rvModules.setAdapter(adapter);
        binding.rvModules.setHasFixedSize(true);
    }

    private void openWebModule(String title, String url) {
        Intent intent = new Intent(requireContext(), ModuleWebActivity.class);
        intent.putExtra(ModuleWebActivity.EXTRA_TITLE, title);
        intent.putExtra(ModuleWebActivity.EXTRA_URL, url);
        startActivity(intent);
        requireActivity().overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
    }

    private List<ModuleItem> buildModuleList() {
        List<ModuleItem> list = new ArrayList<>();

        list.add(new ModuleItem(
                "शालेय परिपाठ", "Daily School Assembly",
                "राष्ट्रगीत, सुविचार, पंचांग, दिनविशेष", "मोफत",
                BASE + "modules/paripath/index.php",
                R.drawable.ic_module_paripath, R.color.module_paripath_bg, R.color.module_paripath_icon,
                true, false));

        list.add(new ModuleItem(
                "HPC प्रगती पत्रक", "Holistic Progress Card",
                "NEP 2020 समग्र प्रगती पत्रक — इ. १ ते ८", "NEP 2020",
                BASE + "hpc/index.php",
                R.drawable.ic_module_hpc, R.color.module_hpc_bg, R.color.module_hpc_icon,
                false, false));

        list.add(new ModuleItem(
                "CCE मूल्यमापन", "Continuous Evaluation",
                "आकारिक + संकलित गुण नोंद, निकाल पत्रक", "CCE ९.०",
                BASE + "cce/index.php",
                R.drawable.ic_module_cce, R.color.module_cce_bg, R.color.module_cce_icon,
                false, false));

        list.add(new ModuleItem(
                "टाचण (दैनिक)", "Lesson Diary",
                "दैनंदिन पाठ नियोजन, वार्षिक टाचण वही", "दैनिक",
                BASE + "modules/tachan/index.php",
                R.drawable.ic_module_tachan, R.color.module_tachan_bg, R.color.module_tachan_icon,
                false, false));

        list.add(new ModuleItem(
                "हजेरी", "Attendance",
                "विद्यार्थी व शिक्षक डिजिटल हजेरी नोंद", "दैनिक",
                BASE + "modules/hajeri/index.php",
                R.drawable.ic_module_hajeri, R.color.module_hajeri_bg, R.color.module_hajeri_icon,
                false, false));

        list.add(new ModuleItem(
                "पोषण आहार (MDM)", "Mid-Day Meal",
                "दैनिक भोजन नोंद, धान्य शिल्लक नोंदवही", "MDM",
                BASE + "modules/mdm/dashboard.php",
                R.drawable.ic_module_mdm, R.color.module_mdm_bg, R.color.module_mdm_icon,
                false, false));

        list.add(new ModuleItem(
                "फी व्यवस्थापन", "Fee Management",
                "शुल्क संकलन, पावती, शिल्लक अहवाल", "हिशोब",
                BASE + "modules/fee/index.php",
                R.drawable.ic_module_fee, R.color.module_fee_bg, R.color.module_fee_icon,
                false, false));

        list.add(new ModuleItem(
                "प्रश्नपत्रिका", "Exam Paper Generator",
                "५०,०००+ प्रश्न, स्वयंचलित पेपर निर्मिती", "५०,०००+",
                BASE + "modules/exam_paper/index.php",
                R.drawable.ic_module_exam, R.color.module_exam_bg, R.color.module_exam_icon,
                false, false));

        list.add(new ModuleItem(
                "विद्यार्थी व्यवस्थापन", "Students",
                "विद्यार्थी नोंदणी, माहिती, APAAR ID", "GR/नोंदणी",
                BASE + "students/list.php",
                R.drawable.ic_module_students, R.color.module_class_bg, R.color.module_class_icon,
                false, false));

        list.add(new ModuleItem(
                "वर्ग व्यवस्थापन", "Class Management",
                "वर्ग, तुकड्या, वेळापत्रक, बोनाफाईड", "केंद्र",
                BASE + "modules/classes/index.php",
                R.drawable.ic_module_class, R.color.module_class_bg, R.color.module_class_icon,
                false, false));

        list.add(new ModuleItem(
                "बोनाफाईड दाखला", "Bonafide Certificate",
                "१-क्लिक डिजिटल बोनाफाईड प्रमाणपत्र", "दाखला",
                BASE + "modules/classes/bonafide.php",
                R.drawable.ic_module_bonafide, R.color.module_gr_bg, R.color.module_gr_icon,
                false, false));

        list.add(new ModuleItem(
                "जनरल रजिस्टर", "General Register",
                "GR — डिजिटल दाखला व प्रवेश पत्रिका", "GR",
                BASE + "modules/classes/index.php",
                R.drawable.ic_module_gr, R.color.module_gr_bg, R.color.module_gr_icon,
                false, false));

        return list;
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
