package com.techguruji.smartschoolhub.ui.modules;

import android.content.Intent;

import androidx.annotation.IdRes;
import androidx.fragment.app.FragmentActivity;
import androidx.navigation.NavController;
import androidx.navigation.Navigation;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.ModuleItem;
import com.techguruji.smartschoolhub.ui.bonafide.BonafideActivity;
import com.techguruji.smartschoolhub.ui.classes.ClassesActivity;
import com.techguruji.smartschoolhub.ui.exam.ExamPaperActivity;
import com.techguruji.smartschoolhub.ui.fee.FeeActivity;
import com.techguruji.smartschoolhub.ui.gr.GrRegisterActivity;
import com.techguruji.smartschoolhub.ui.hajeri.HajeriActivity;
import com.techguruji.smartschoolhub.ui.mdm.MdmActivity;
import com.techguruji.smartschoolhub.ui.tachan.TachanActivity;

/**
 * Single place that maps a module key to its native screen.
 * Every module opens a native Activity or a nav-graph Fragment — no WebView.
 */
public final class ModuleRouter {

    private ModuleRouter() {}

    public static void open(FragmentActivity activity, String key) {
        switch (key) {
            case ModuleItem.KEY_PARIPATH:
                navigate(activity, R.id.paripathFragment);
                break;
            case ModuleItem.KEY_HPC:
                navigate(activity, R.id.hpcFragment);
                break;
            case ModuleItem.KEY_CCE:
                navigate(activity, R.id.cceFragment);
                break;
            case ModuleItem.KEY_STUDENTS:
                navigate(activity, R.id.studentsFragment);
                break;
            case ModuleItem.KEY_HAJERI:
                start(activity, HajeriActivity.class);
                break;
            case ModuleItem.KEY_TACHAN:
                start(activity, TachanActivity.class);
                break;
            case ModuleItem.KEY_MDM:
                start(activity, MdmActivity.class);
                break;
            case ModuleItem.KEY_FEE:
                start(activity, FeeActivity.class);
                break;
            case ModuleItem.KEY_EXAM:
                start(activity, ExamPaperActivity.class);
                break;
            case ModuleItem.KEY_CLASSES:
                start(activity, ClassesActivity.class);
                break;
            case ModuleItem.KEY_BONAFIDE:
                start(activity, BonafideActivity.class);
                break;
            case ModuleItem.KEY_GR:
                start(activity, GrRegisterActivity.class);
                break;
            default:
                break;
        }
    }

    private static void navigate(FragmentActivity activity, @IdRes int destination) {
        NavController controller = Navigation.findNavController(activity, R.id.nav_host_fragment);
        if (controller.getCurrentDestination() == null
                || controller.getCurrentDestination().getId() != destination) {
            controller.navigate(destination);
        }
    }

    private static void start(FragmentActivity activity, Class<?> target) {
        activity.startActivity(new Intent(activity, target));
        activity.overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
    }
}
