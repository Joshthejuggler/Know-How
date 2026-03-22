<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles Role-Based Access Control (RBAC) for the platform.
 */
class MC_Roles
{
    const ROLE_EMPLOYER = 'mc_employer';
    const ROLE_EMPLOYEE = 'mc_employee';

    // Capabilities
    const CAP_MANAGE_EMPLOYEES = 'mc_manage_employees';
    const CAP_VIEW_SENSITIVE_REPORTS = 'mc_view_sensitive_reports';
    const CAP_GENERATE_EXPERIMENTS = 'mc_generate_experiments';
    const CAP_TAKE_ASSESSMENTS = 'mc_take_assessments';
    const CAP_VIEW_OWN_RESULTS = 'mc_view_own_results';

    /**
     * Initialize roles and capabilities.
     */
    public static function init()
    {
        add_action('init', [__CLASS__, 'register_roles']);
        add_action('admin_init', [__CLASS__, 'add_caps_to_admin']);
    }

    /**
     * Register custom roles.
     */
    public static function register_roles()
    {
        if (!get_role(self::ROLE_EMPLOYER)) {
            add_role(self::ROLE_EMPLOYER, 'Employer', [
                'read' => true,
                self::CAP_MANAGE_EMPLOYEES => true,
                self::CAP_VIEW_SENSITIVE_REPORTS => true,
                self::CAP_GENERATE_EXPERIMENTS => true,
                // Employers generally don't take assessments in this context, 
                // but might want to test them. For now, let's keep it separate.
                self::CAP_TAKE_ASSESSMENTS => false, 
            ]);
        }

        if (!get_role(self::ROLE_EMPLOYEE)) {
            add_role(self::ROLE_EMPLOYEE, 'Employee', [
                'read' => true,
                self::CAP_TAKE_ASSESSMENTS => true,
                self::CAP_VIEW_OWN_RESULTS => true,
                // Explicitly deny employer caps
                self::CAP_MANAGE_EMPLOYEES => false,
                self::CAP_VIEW_SENSITIVE_REPORTS => false,
                self::CAP_GENERATE_EXPERIMENTS => false,
            ]);
        }
    }

    /**
     * Ensure admins have all custom capabilities.
     */
    public static function add_caps_to_admin()
    {
        $admin = get_role('administrator');
        if ($admin) {
            $caps = [
                self::CAP_MANAGE_EMPLOYEES,
                self::CAP_VIEW_SENSITIVE_REPORTS,
                self::CAP_GENERATE_EXPERIMENTS,
                self::CAP_TAKE_ASSESSMENTS,
                self::CAP_VIEW_OWN_RESULTS
            ];
            foreach ($caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }
    }

    /**
     * Run migration to assign roles to existing users.
     * Should be triggered manually or on activation.
     */
    public static function migrate_users()
    {
        $args = [
            'role__not_in' => [self::ROLE_EMPLOYER, self::ROLE_EMPLOYEE, 'administrator'],
            'number' => -1,
            'fields' => 'all'
        ];
        $users = get_users($args);

        $count_employer = 0;
        $count_employee = 0;

        foreach ($users as $user) {
            $linked_employer = get_user_meta($user->ID, 'mc_linked_employer_id', true);
            
            if (!empty($linked_employer)) {
                // It's an employee
                $user->set_role(self::ROLE_EMPLOYEE);
                $count_employee++;
            } else {
                // It's likely an employer (or a standalone user acting as one)
                // Default to employer for legacy users
                $user->set_role(self::ROLE_EMPLOYER);
                $count_employer++;
            }
        }

        return [
            'employers' => $count_employer,
            'employees' => $count_employee
        ];
    }
}
