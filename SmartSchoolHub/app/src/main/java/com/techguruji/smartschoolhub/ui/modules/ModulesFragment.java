package com.techguruji.smartschoolhub.ui.modules;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.GridLayoutManager;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.adapter.ModuleAdapter;
import com.techguruji.smartschoolhub.data.model.ModuleItem;
import com.techguruji.smartschoolhub.databinding.FragmentModulesBinding;

import java.util.ArrayList;
import java.util.List;

/**
 * ModulesFragment — full grid of all 12 school modules.
 * Every module opens a native Android screen via ModuleRouter.
 */
public class ModulesFragment extends Fragment {

    private FragmentModulesBinding binding;

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

        ModuleAdapter adapter = new ModuleAdapter(modules,
                item -> ModuleRouter.open(requireActivity(), item.getKey()));

        binding.rvModules.setLayoutManager(new GridLayoutManager(requireContext(), 2));
        binding.rvModules.setAdapter(adapter);
        binding.rvModules.setHasFixedSize(true);
    }

    private List<ModuleItem> buildModuleList() {
        List<ModuleItem> list = new ArrayList<>();

        list.add(new ModuleItem(
                ModuleItem.KEY_PARIPATH,
                "शालेय परिपाठ", "Daily School Assembly",
                "राष्ट्रगीत, सुविचार, पंचांग, दिनविशेष", "मोफत",
                R.drawable.ic_module_paripath, R.color.module_paripath_bg, R.color.module_paripath_icon,
                true, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_HPC,
                "HPC प्रगती पत्रक", "Holistic Progress Card",
                "NEP 2020 समग्र प्रगती पत्रक — इ. १ ते ८", "NEP 2020",
                R.drawable.ic_module_hpc, R.color.module_hpc_bg, R.color.module_hpc_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_CCE,
                "CCE मूल्यमापन", "Continuous Evaluation",
                "आकारिक + संकलित गुण नोंद, निकाल पत्रक", "CCE ९.०",
                R.drawable.ic_module_cce, R.color.module_cce_bg, R.color.module_cce_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_TACHAN,
                "टाचण (दैनिक)", "Lesson Diary",
                "दैनंदिन पाठ नियोजन, वार्षिक टाचण वही", "दैनिक",
                R.drawable.ic_module_tachan, R.color.module_tachan_bg, R.color.module_tachan_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_HAJERI,
                "हजेरी", "Attendance",
                "विद्यार्थी व शिक्षक डिजिटल हजेरी नोंद", "दैनिक",
                R.drawable.ic_module_hajeri, R.color.module_hajeri_bg, R.color.module_hajeri_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_MDM,
                "पोषण आहार (MDM)", "Mid-Day Meal",
                "दैनिक भोजन नोंद, धान्य शिल्लक नोंदवही", "MDM",
                R.drawable.ic_module_mdm, R.color.module_mdm_bg, R.color.module_mdm_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_FEE,
                "फी व्यवस्थापन", "Fee Management",
                "शुल्क संकलन, पावती, शिल्लक अहवाल", "हिशोब",
                R.drawable.ic_module_fee, R.color.module_fee_bg, R.color.module_fee_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_EXAM,
                "प्रश्नपत्रिका", "Exam Paper Generator",
                "५०,०००+ प्रश्न, स्वयंचलित पेपर निर्मिती", "५०,०००+",
                R.drawable.ic_module_exam, R.color.module_exam_bg, R.color.module_exam_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_STUDENTS,
                "विद्यार्थी व्यवस्थापन", "Students",
                "विद्यार्थी नोंदणी, माहिती, APAAR ID", "GR/नोंदणी",
                R.drawable.ic_module_students, R.color.module_class_bg, R.color.module_class_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_CLASSES,
                "वर्ग व्यवस्थापन", "Class Management",
                "वर्ग, तुकड्या, वेळापत्रक, बोनाफाईड", "केंद्र",
                R.drawable.ic_module_class, R.color.module_class_bg, R.color.module_class_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_BONAFIDE,
                "बोनाफाईड दाखला", "Bonafide Certificate",
                "१-क्लिक डिजिटल बोनाफाईड प्रमाणपत्र", "दाखला",
                R.drawable.ic_module_bonafide, R.color.module_gr_bg, R.color.module_gr_icon,
                false, false));

        list.add(new ModuleItem(
                ModuleItem.KEY_GR,
                "जनरल रजिस्टर", "General Register",
                "GR — डिजिटल दाखला व प्रवेश पत्रिका", "GR",
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
