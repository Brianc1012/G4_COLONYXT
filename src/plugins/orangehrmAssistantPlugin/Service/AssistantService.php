<?php
namespace OrangeHRM\Assistant\Service;

use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Admin\Traits\Service\UserServiceTrait;
use OrangeHRM\Leave\Service\LeaveEntitlementService;
use OrangeHRM\Leave\Service\LeaveRequestService;
use OrangeHRM\Leave\Service\LeaveTypeService;
use OrangeHRM\Time\Service\TimesheetService;
use OrangeHRM\Pim\Service\EmployeeService;
use OrangeHRM\Dashboard\Service\EmployeeTimeAtWorkService;
use OrangeHRM\Claim\Service\ClaimService;
use OrangeHRM\Time\Dto\TimesheetSearchFilterParams;
use OrangeHRM\Leave\Dto\LeaveRequestSearchFilterParams;
use DateTime;

class AssistantService
{
    use AuthUserTrait;
    use UserServiceTrait;

    private array $intents;

    public function __construct()
    {
        $this->intents = $this->loadIntents();
    }

    private function currentUsername(): ?string
    {
        try {
            $user = $this->getUserService()->getSystemUser($this->getAuthUser()->getUserId());
            if ($user) {
                return method_exists($user, 'getUserName') ? $user->getUserName() : (method_exists($user, 'getUsername') ? $user->getUsername() : null);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    public function handleMessage(string $message, ?int $empNumber, ?int $userRoleId): array
    {
        $message = trim(mb_strtolower($message));
        $intent = $this->matchIntent($message);

        if (!$intent) {
            return $this->outOfScope();
        }

        // Non-admin scope only: if roleId == 1 (admin) limit to FAQ only
        $isAdmin = ($userRoleId === 1);

        switch ($intent) {
            case 'leave_balance':
                if ($isAdmin) return $this->faq('leave_balance');
                return $this->getLeaveBalance($empNumber);
            case 'my_leave_status':
                if ($isAdmin) return $this->faq('my_leave_status');
                return $this->getMyRecentLeaveStatus($empNumber);
            case 'timesheet_this_week':
                if ($isAdmin) return $this->faq('timesheet_this_week');
                return $this->getTimesheetThisWeek($empNumber);
            case 'my_info_summary':
                if ($isAdmin) return $this->faq('my_info_summary');
                return $this->getMyInfoSummary($empNumber, $message);
            case 'my_identity':
                if ($isAdmin) return $this->faq('my_identity');
                return $this->getMyIdentity($empNumber, $message);
            case 'password_help':
                return $this->passwordHelp();
            case 'performance_help':
            case 'dashboard_help':
            case 'directory_help':
            case 'claim_help':
            case 'buzz_help':
            case 'time_help':
            case 'leave_help':
                return $this->faq($intent);
            default:
                return $this->faq($intent);
        }
    }

    private function loadIntents(): array
    {
        $file = __DIR__ . '/../config/intents.yaml';
        if (file_exists($file)) {
            // naive YAML loader to avoid extra deps: expects key: [phrases]
            $yaml = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $intents = [];
            $current = null;
            foreach ($yaml as $line) {
                if (preg_match('/^([a-zA-Z0-9_]+):\s*$/', trim($line), $m)) {
                    $current = $m[1];
                    $intents[$current] = [];
                } elseif ($current && preg_match('/^-\s*(.+)$/', trim($line), $m)) {
                    $intents[$current][] = mb_strtolower(trim($m[1]));
                }
            }
            return $intents;
        }
        // Built-in minimal intents
        return [
            'leave_balance' => ['leave balance', 'how many leave days', 'leave days left', 'my leave balance'],
            'my_leave_status' => ['status of my leave', 'my leave requests status'],
            'timesheet_this_week' => ['timesheet for this week', 'hours this week', 'show my timesheet'],
            'my_info_summary' => ['my job title', 'my department', 'who is my manager', 'my employee id'],
            'my_identity' => ['my username', "what's my username", 'who am i', 'my first name', 'my last name', 'my full name'],
            'performance_help' => ['performance help', 'how performance works', 'how to view performance review'],
            'dashboard_help' => ['dashboard help', 'how to use dashboard', 'widgets on dashboard'],
            'directory_help' => ['directory help', 'search directory', 'find an employee'],
            'claim_help' => ['claim help', 'how to submit claim', 'how to view my claims'],
            'buzz_help' => ['buzz help', 'post on buzz', 'how to use buzz'],
            'time_help' => ['time help', 'timesheet help', 'how to fill timesheet'],
            'leave_help' => ['leave help', 'how to request leave', 'apply for leave'],
            'apply_leave' => ['apply for leave', 'how to apply leave'],
        ];
    }

    private function matchIntent(string $message): ?string
    {
        foreach ($this->intents as $intent => $phrases) {
            foreach ($phrases as $p) {
                if (mb_strpos($message, $p) !== false) return $intent;
            }
        }
        return null;
    }

    private function faq(string $intent): array
    {
        $username = $this->currentUsername() ?? 'there';
        $answers = [
            'apply_leave' => "Hello $username, to apply for leave: 1) Go to Leave > Apply, 2) choose the leave type and dates, 3) add a note (optional), 4) Submit. Track status in Leave > My Leave.",
            'leave_balance' => "Hello $username, see entitlements and usage at Leave > My Leave Entitlements and Usage.",
            'my_leave_status' => "Hello $username, review your request statuses in Leave > My Leave (filter Approved/Pending/Rejected).",
            'timesheet_this_week' => "Hello $username, open Time > My Timesheets to add or edit entries, then Submit for approval.",
            'my_info_summary' => "Hello $username, find your personal details in My Info; ask for a specific item like job title or birthday.",
            'my_identity' => "Hello $username, your full name is in My Info > Personal Details; username is in the top-right profile menu.",
            'performance_help' => "Hello $username, check Performance > My Reviews and My Trackers to view goals and reviews.",
            'dashboard_help' => "Hello $username, the Dashboard shows Time at Work, My Actions, and Quick Launch tiles.",
            'directory_help' => "Hello $username, use Directory to find employees by name, job title, or location.",
            'claim_help' => "Hello $username, to submit a claim: Claim > My Claims > Add, fill details and attachments, then Submit. Track in the same page.",
            'buzz_help' => "Hello $username, to post in Buzz: open Buzz, write your message (add images if needed), then Post.",
            'time_help' => "Hello $username, fill your hours in Time > My Timesheets; save daily, then Submit the week.",
            'attendance_help' => "Hello $username, manage attendance in Time > Attendance. Use Punch In/Out to record your time.",
            'leave_help' => "Hello $username, apply via Leave > Apply; track in Leave > My Leave.",
            'password_help' => "Hello $username, I can’t help with passwords as they’re sensitive. If you’ve forgotten yours, click ‘Forgot your password?’ on the login page to reset it. If you still can’t sign in, contact your administrator or HR to assist with a reset.",
        ];
        return [
            'reply' => $answers[$intent] ?? "Hello $username, please check the relevant module for steps.",
            'intent' => $intent,
        ];
    }

    private function passwordHelp(): array
    {
        $username = $this->currentUsername() ?? 'there';
        return [
            'reply' => "Hello $username, I can’t provide or view passwords. If you forgot yours, use ‘Forgot your password?’ on the login screen to reset it. For further help, contact your administrator or HR.",
            'intent' => 'password_help',
        ];
    }

    private function getLeaveBalance(?int $empNumber): array
    {
        $entitlementService = new LeaveEntitlementService();
        $leaveTypeService = new LeaveTypeService();
        // Summarize across eligible leave types as at today
        $types = $leaveTypeService->getLeaveTypeDao()->getLeaveTypeList();
        $now = new DateTime();
        $parts = [];
        foreach ($types as $type) {
            $bal = $entitlementService->getLeaveBalance($empNumber, $type->getId(), $now, null);
            $parts[] = sprintf('%s %s', $type->getName(), rtrim(rtrim(number_format((float)$bal->getBalance(), 2, '.', ''), '0'), '.'));
        }
        $username = $this->currentUsername() ?? 'there';
        $list = empty($parts) ? 'no balances found' : implode(', ', $parts);
        return [
            'reply' => sprintf('Hello %s, your leave balances are: <b>%s</b>.', $username, $list),
            'intent' => 'leave_balance',
        ];
    }

    private function getMyRecentLeaveStatus(?int $empNumber): array
    {
        $service = new LeaveRequestService();
        $dao = $service->getLeaveRequestDao();
        $from = (new DateTime('first day of -2 months'))->setTime(0, 0, 0);
        $to = (new DateTime('last day of this month'))->setTime(23, 59, 59);
        $requests = $dao->getLeaveRequestsByEmpNumberAndDateRange($empNumber, $from, $to);
        // Sort desc by dateApplied and take latest 5
        usort($requests, function ($a, $b) { return $b->getDateApplied() <=> $a->getDateApplied(); });
        $username = $this->currentUsername() ?? 'there';
        if (empty($requests)) {
            return [
                'reply' => sprintf('Hello %s, you have no recent leave requests.', $username),
                'intent' => 'my_leave_status',
            ];
        }
        $req = $requests[0];
        $latestStatus = null;
        $latestDate = null;
        foreach ($req->getLeaves() as $leave) {
            $d = $leave->getDate();
            if ($latestDate === null || $d > $latestDate) {
                $latestDate = $d;
                $latestStatus = $service->getLeaveStatusNameByStatus($leave->getStatus());
            }
        }
        return [
            'reply' => sprintf('Hello %s, your most recent leave request is <b>%s</b> (type <b>%s</b>, applied on <b>%s</b>).',
                $username,
                $latestStatus ?? 'Unknown',
                $req->getLeaveType() ? $req->getLeaveType()->getName() : 'N/A',
                $req->getDateApplied()->format('Y-m-d')
            ),
            'intent' => 'my_leave_status',
        ];
    }

    private function getTimesheetThisWeek(?int $empNumber): array
    {
        $service = new TimesheetService();
        [$startDateStr, $endDateStr] = $service->extractStartDateAndEndDateFromDate(new DateTime());
        $startDate = new DateTime($startDateStr);
        $endDate = new DateTime($endDateStr);

        $filter = new TimesheetSearchFilterParams();
        $filter->setEmpNumber($empNumber);
        $filter->setFromDate($startDate);
        $filter->setToDate($endDate);
        $timesheets = $service->getTimesheetDao()->getTimesheetByStartAndEndDate($filter);

        $total = 0.0;
        if (!empty($timesheets)) {
            $timesheet = $timesheets[0];
            $entries = $service->getTimesheetDao()->getTimesheetItemsByTimesheetId($timesheet->getId());
            foreach ($entries as $e) {
                if (is_null($e->getDuration())) continue;
                $hours = $e->getDuration() / 3600.0;
                $total += $hours;
            }
        }
        $username = $this->currentUsername() ?? 'there';
        return [
            'reply' => sprintf('Hello %s, you’ve logged <b>%.2f</b> hours from <b>%s</b> to <b>%s</b>.', $username, $total, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')),
            'intent' => 'timesheet_this_week',
        ];
    }

    private function getMyInfoSummary(?int $empNumber, ?string $message = null): array
    {
        $service = new EmployeeService();
        $employee = $service->getEmployeeByEmpNumber($empNumber);
        $username = $this->currentUsername() ?? 'there';
        if (!$employee) {
            return ['reply' => sprintf('Hello %s, your employee record was not found.', $username), 'intent' => 'my_info_summary'];
        }

        $msg = $message ? mb_strtolower($message) : '';
        $respondNotDefined = function (string $field) use ($username): array {
            $guidance = '';
            switch ($field) {
                case 'job title':
                    $guidance = 'Please contact HR to update your Job Title (visible in My Info > Job).';
                    break;
                case 'department':
                    $guidance = 'Please contact HR to set your Department (visible in My Info > Job).';
                    break;
                case 'manager':
                    $guidance = 'Ask HR to assign a Supervisor (visible in My Info > Reports To).';
                    break;
                case 'employee id':
                    $guidance = 'Ask HR to set your Employee ID (visible in My Info > Personal Details).';
                    break;
                case 'birthday':
                    $guidance = 'Go to My Info > Personal Details to add your Date of Birth (if permitted), or contact HR.';
                    break;
                default:
                    $guidance = 'Go to My Info to update this field, or contact HR.';
            }
            return [
                'reply' => sprintf('Hello %s, your %s is currently not defined. %s', $username, $field, $guidance),
                'intent' => 'my_info_summary',
            ];
        };

        // Detect which single field is requested
        if ($msg !== '') {
            if (mb_strpos($msg, 'job title') !== false || mb_strpos($msg, 'jobtitle') !== false) {
                $value = $employee->getJobTitle() ? $employee->getJobTitle()->getJobTitleName() : null;
                if (!$value) return $respondNotDefined('job title');
                return ['reply' => sprintf('Hello %s, your job title is <b>%s</b>.', $username, $value), 'intent' => 'my_info_summary'];
            }
            if (mb_strpos($msg, 'department') !== false || mb_strpos($msg, 'dept') !== false) {
                $value = $employee->getSubDivision() ? $employee->getSubDivision()->getName() : null;
                if (!$value) return $respondNotDefined('department');
                return ['reply' => sprintf('Hello %s, your department is <b>%s</b>.', $username, $value), 'intent' => 'my_info_summary'];
            }
            if (mb_strpos($msg, 'manager') !== false || mb_strpos($msg, 'supervisor') !== false) {
                $supervisors = method_exists($employee, 'getSupervisors') ? $employee->getSupervisors() : [];
                $manager = null;
                foreach ($supervisors as $sup) {
                    $mn = trim(($sup->getFirstName() ?? '') . ' ' . ($sup->getLastName() ?? ''));
                    if ($mn !== '') { $manager = $mn; break; }
                }
                if (!$manager) return $respondNotDefined('manager');
                return ['reply' => sprintf('Hello %s, your manager is <b>%s</b>.', $username, $manager), 'intent' => 'my_info_summary'];
            }
            if (mb_strpos($msg, 'employee id') !== false || mb_strpos($msg, 'employeeid') !== false || mb_strpos($msg, 'emp id') !== false || mb_strpos($msg, 'empid') !== false) {
                $value = $employee->getEmployeeId();
                if (!$value) return $respondNotDefined('employee id');
                return ['reply' => sprintf('Hello %s, your employee ID is <b>%s</b>.', $username, $value), 'intent' => 'my_info_summary'];
            }
            if (mb_strpos($msg, 'birthday') !== false || mb_strpos($msg, 'birthdate') !== false || mb_strpos($msg, 'date of birth') !== false || mb_strpos($msg, 'dob') !== false) {
                $dob = method_exists($employee, 'getBirthday') ? $employee->getBirthday() : null;
                $value = $dob ? $dob->format('Y-m-d') : null;
                if (!$value) return $respondNotDefined('birthday');
                return ['reply' => sprintf('Hello %s, your birthdate is <b>%s</b>.', $username, $value), 'intent' => 'my_info_summary'];
            }
        }

        return [
            'reply' => sprintf('Hello %s, please ask for a specific detail like job title, department, manager, employee ID, or birthday.', $username),
            'intent' => 'my_info_summary',
        ];
    }

    private function getMyIdentity(?int $empNumber, ?string $message = null): array
    {
        $employeeService = new EmployeeService();
        $employee = $employeeService->getEmployeeByEmpNumber($empNumber);

        $username = $this->currentUsername();

        $data = [
            'username' => $username,
            'firstName' => $employee ? $employee->getFirstName() : null,
            'middleName' => $employee && method_exists($employee, 'getMiddleName') ? $employee->getMiddleName() : null,
            'lastName' => $employee ? $employee->getLastName() : null,
            'fullName' => $employee ? trim(implode(' ', array_filter([
                $employee->getFirstName() ?? '',
                method_exists($employee, 'getMiddleName') ? ($employee->getMiddleName() ?? '') : '',
                $employee->getLastName() ?? ''
            ], function($v){ return $v !== null && trim($v) !== ''; }))) : null,
            'employeeId' => $employee ? $employee->getEmployeeId() : null,
        ];

        // If a specific identity field was asked, reply with only that value (no bulky data payload)
        $target = null;
        $msg = $message ? mb_strtolower($message) : '';
        if ($msg !== '') {
            if (mb_strpos($msg, 'username') !== false) {
                $target = 'username';
            } elseif (mb_strpos($msg, 'first name') !== false || mb_strpos($msg, 'firstname') !== false) {
                $target = 'firstName';
            } elseif (mb_strpos($msg, 'middle name') !== false || mb_strpos($msg, 'middlename') !== false) {
                $target = 'middleName';
            } elseif (mb_strpos($msg, 'last name') !== false || mb_strpos($msg, 'lastname') !== false) {
                $target = 'lastName';
            } elseif (mb_strpos($msg, 'full name') !== false || mb_strpos($msg, 'fullname') !== false || mb_strpos($msg, 'who am i') !== false || mb_strpos($msg, 'whoami') !== false || preg_match('/\bwhat\s*is\s*my\s*name\b/', $msg) === 1 || preg_match('/\bmy\s*name\b/', $msg) === 1) {
                $target = 'fullName';
            }
        }

        if ($target) {
            $value = $data[$target] ?? null;
            $uname = $username ?? 'there';
            if ($value === null || $value === '') {
                $fieldLabel = $target === 'firstName' ? 'first name' : ($target === 'lastName' ? 'last name' : ($target === 'middleName' ? 'middle name' : ($target === 'fullName' ? 'full name' : 'username')));
                $guidance = $target === 'username' ? 'Please contact an administrator to set your username.' : 'Go to My Info > Personal Details to set this.';
                return [
                    'reply' => sprintf('Hello %s, your %s is currently not defined. %s', $uname, $fieldLabel, $guidance),
                    'intent' => 'my_identity',
                ];
            }
            $label = $target === 'firstName' ? 'first name' : ($target === 'lastName' ? 'last name' : ($target === 'middleName' ? 'middle name' : ($target === 'fullName' ? 'full name' : 'username')));
            return [
                'reply' => sprintf('Hello %s, your %s is <b>%s</b>.', $uname, $label, (string)$value),
                'intent' => 'my_identity',
            ];
        }

        // Generic identity query: return a concise summary with structured data
        $uname = $username ?? 'there';
        return [
            'reply' => sprintf('Hello %s, please ask for your username, first name, last name, or full name.', $uname),
            'intent' => 'my_identity',
        ];
    }

    private function outOfScope(): array
    {
        $username = $this->currentUsername() ?? 'there';
        return [
            'reply' => sprintf('Hello %s, I can help with Leave, Time, My Info, Performance, Dashboard, Directory, Claim, and Buzz. Try: "Show my leave balance", "This week timesheet", or ask for a specific detail like "my job title".', $username),
            'intent' => null,
        ];
    }
}
