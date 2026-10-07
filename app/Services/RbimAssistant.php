<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\DocumentRequest;
use App\Models\MigrationRecord;
use App\Models\User;

class RbimAssistant
{
    /**
     * Return a closed-domain, role-aware RBIM response.
     *
     * @return array{reply: string, suggestions: array<int, string>, actions: array<int, array{label: string, url: string}>, scope: string}
     */
    public function respond(User $user, string $message): array
    {
        $documentStatus = app(AssistantDocumentStatus::class)->respond($user, $message);
        if ($documentStatus !== null) return $documentStatus;

        $guide = app(AssistantGuide::class)->respond($user, $message);
        if ($guide !== null) return $guide;

        $report = app(AssistantReportSummary::class)->respond($user, $message);
        if ($report !== null) return $report;

        $message = mb_strtolower($message);

        if ($this->matches($message, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            return $this->response(
                AssistantLanguage::text('Hello, :name. I’m the RBIM Assistant. I can help with the features and records available to your :role account.', ['name' => $user->name, 'role' => $user->roleLabel()]),
                $this->suggestions($user),
                $this->dashboardAction($user)
            );
        }

        if ($this->matches($message, ['what can you do', 'help', 'options', 'features', 'how can you help'])) {
            return $this->capabilities($user);
        }

        if ($this->matches($message, ['my account', 'my profile', 'who am i', 'account status', 'my barangay'])) {
            $barangay = $user->barangay?->name ? 'Barangay '.$user->barangay->name : AssistantLanguage::text('no assigned barangay');

            return $this->response(
                AssistantLanguage::text('You are signed in as :name, a :role, with :barangay. Your account status is :status.', ['name' => $user->name, 'role' => $user->roleLabel(), 'barangay' => $barangay, 'status' => $user->approvalStatusLabel()]),
                $this->suggestions($user),
                $this->dashboardAction($user)
            );
        }

        if ($user->hasRole(User::ROLE_BARANGAY) && ! $user->isApproved()) {
            return $this->response(AssistantLanguage::text('Your barangay account must be approved before staff records are available.'), ['What is my account status?'], []);
        }

        if ($this->matches($message, ['document', 'documents', 'requests', 'certificate', 'clearance', 'indigency', 'request status'])) {
            return $this->documents($user, $message);
        }

        if ($this->matches($message, ['approval', 'approvals', 'approve', 'pending registration', 'pending account', 'verification'])) {
            return $this->approvals($user);
        }

        if ($this->matches($message, ['rbi', 'monthly form', 'submission', 'submitted report', 'draft form'])) {
            return $this->rbi($user);
        }

        if ($this->matches($message, ['registry', 'inhabitant', 'resident count', 'household', 'population'])) {
            return $this->registry($user);
        }

        if ($this->matches($message, ['migration', 'movement', 'moved in', 'moved out', 'trend'])) {
            return $this->migration($user);
        }

        if ($this->matches($message, ['dashboard', 'where', 'open', 'go to', 'navigate'])) {
            return $this->navigation($user);
        }

        return $this->response(
            AssistantLanguage::text('I can only help with the RBIM system—accounts, document requests, approvals, registry records, RBI forms, migration monitoring, and the features permitted for your role. I can’t answer topics outside this system.'),
            $this->suggestions($user),
            $this->dashboardAction($user)
        );
    }

    private function capabilities(User $user): array
    {
        $reply = match ($user->role) {
            User::ROLE_MUNICIPAL_LGU => AssistantLanguage::text('I can summarize submitted RBI forms, municipality-wide registry and migration totals, pending secretary approvals, and guide you to municipal dashboards.'),
            User::ROLE_BARANGAY => AssistantLanguage::text('I can summarize your barangay registry, resident approvals, document requests, RBI forms, and migration records, and guide you to the correct workspace.'),
            default => AssistantLanguage::text('I can explain resident services, show the status of your own document requests, confirm your account details, and guide you around the Resident Portal.'),
        };

        return $this->response($reply, $this->suggestions($user), $this->dashboardAction($user));
    }

    private function documents(User $user, string $message): array
    {
        if ($user->hasAnyRole([User::ROLE_RESIDENT, User::ROLE_BARANGAY]) && ! $user->isApproved()) {
            return $this->response(AssistantLanguage::text('An approved account is required to view document and payment status records.'), ['What is my account status?'], []);
        }
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            $requests = $user->documentRequests()->latest()->get();
            $pending = $requests->whereIn('status', [DocumentRequest::STATUS_PENDING, DocumentRequest::STATUS_PROCESSING])->count();
            $ready = $requests->where('status', DocumentRequest::STATUS_READY)->count();
            $latest = $requests->first();
            $latestText = $latest
                ? AssistantLanguage::text(' Your latest request is :reference (:type), currently :status.', ['reference' => $latest->reference_number, 'type' => $latest->typeLabel(), 'status' => $latest->statusLabel()]).$this->residentPaymentSummary($latest)
                : AssistantLanguage::text(' You have not submitted a document request yet.');

            return $this->response(
                AssistantLanguage::text('You have :total document request(s): :pending pending or processing and :ready ready for release.', ['total' => $requests->count(), 'pending' => $pending, 'ready' => $ready]).$latestText.AssistantLanguage::text(' Use the Resident Portal form to request a barangay document.'),
                [AssistantLanguage::text('How do I request a document?'), AssistantLanguage::text('What is my account status?')],
                [['label' => AssistantLanguage::text('Open Resident Portal'), 'url' => route('dashboard.resident')]]
            );
        }

        if ($user->hasRole(User::ROLE_BARANGAY)) {
            if (! $user->barangay_id) {
                return $this->missingBarangay($user);
            }

            $requests = DocumentRequest::where('barangay_id', $user->barangay_id)->get();
            $pending = $requests->where('status', DocumentRequest::STATUS_PENDING)->count();
            $processing = $requests->where('status', DocumentRequest::STATUS_PROCESSING)->count();
            $ready = $requests->where('status', DocumentRequest::STATUS_READY)->count();

            return $this->response(
                AssistantLanguage::text('Barangay :name has :total document request(s): :pending pending review, :processing processing, and :ready ready for release. Process them in the Barangay Dashboard.', ['name' => $user->barangay->name, 'total' => $requests->count(), 'pending' => $pending, 'processing' => $processing, 'ready' => $ready]),
                [AssistantLanguage::text('Show pending resident approvals'), AssistantLanguage::text('Summarize our registry')],
                [['label' => AssistantLanguage::text('Open Document Requests'), 'url' => route('dashboard.barangay').'#document-requests-title']]
            );
        }

        return $this->response(
            AssistantLanguage::text('Resident document requests are processed by their assigned barangay. Municipal accounts do not open individual resident requests in this RBIM workspace.'),
            [AssistantLanguage::text('Summarize submitted RBI forms'), AssistantLanguage::text('Show pending secretary approvals')],
            $this->dashboardAction($user)
        );
    }

    private function residentPaymentSummary(DocumentRequest $request): string
    {
        if (! $request->requiresPayment()) {
            return AssistantLanguage::text(' No payment is required.');
        }

        if (! $request->hasAssessedFee()) return AssistantLanguage::text(' The secretary will set the fee before payment.');
        return AssistantLanguage::text(' The amount due is ₱').number_format((float) $request->amount_due, 2).AssistantLanguage::text(' and the payment status is ').$request->paymentStatusLabel().'.';
    }

    private function approvals(User $user): array
    {
        if ($user->hasRole(User::ROLE_MUNICIPAL_LGU)) {
            $count = User::where('role', User::ROLE_BARANGAY)
                ->where('approval_status', User::APPROVAL_PENDING)
                ->count();

            return $this->response(
                AssistantLanguage::text('There are :count barangay secretary account(s) awaiting Municipal LGU verification.', ['count' => $count]),
                [AssistantLanguage::text('Summarize submitted RBI forms'), AssistantLanguage::text('Summarize the municipal registry')],
                [['label' => AssistantLanguage::text('Review Secretary Accounts'), 'url' => route('dashboard.municipal').'#secretary-approvals']]
            );
        }

        if ($user->hasRole(User::ROLE_BARANGAY)) {
            if (! $user->barangay_id) {
                return $this->missingBarangay($user);
            }

            $count = User::where('role', User::ROLE_RESIDENT)
                ->where('barangay_id', $user->barangay_id)
                ->where('approval_status', User::APPROVAL_PENDING)
                ->count();

            return $this->response(
                AssistantLanguage::text('Barangay :name has :count resident registration(s) awaiting verification.', ['name' => $user->barangay->name, 'count' => $count]),
                [AssistantLanguage::text('Summarize document requests'), AssistantLanguage::text('Summarize our registry')],
                [['label' => AssistantLanguage::text('Review Resident Accounts'), 'url' => route('dashboard.barangay').'#resident-approvals-title']]
            );
        }

        return $this->response(
            AssistantLanguage::text('Your resident account status is :status.', ['status' => $user->approvalStatusLabel()]).($user->isApproved() ? AssistantLanguage::text(' You can use the Resident Portal.') : AssistantLanguage::text(' Your assigned barangay must verify the account before portal access is enabled.')),
            [AssistantLanguage::text('Show my document requests'), AssistantLanguage::text('What can you do?')],
            $this->dashboardAction($user)
        );
    }

    private function rbi(User $user): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return $this->restricted(AssistantLanguage::text('RBI forms are staff records and are not available to Resident accounts.'), $user);
        }

        $query = BarangayRbiUpdate::query();
        if ($user->hasRole(User::ROLE_BARANGAY)) {
            $query->where('barangay_user_id', $user->id);
        } else {
            $query->where('status', BarangayRbiUpdate::STATUS_SUBMITTED);
        }

        $forms = $query->latest('updated_at')->get();
        $submitted = $forms->where('status', BarangayRbiUpdate::STATUS_SUBMITTED)->count();
        $drafts = $forms->where('status', BarangayRbiUpdate::STATUS_DRAFT)->count();
        $latest = $forms->first();
        $latestText = $latest
            ? AssistantLanguage::text(' The latest is ').($latest->barangay_name ?: AssistantLanguage::text('an unassigned barangay')).AssistantLanguage::text(' for ').($latest->reporting_month?->format('F Y') ?: AssistantLanguage::text('an unset month')).'.'
            : AssistantLanguage::text(' No RBI form is available yet.');

        $reply = $user->hasRole(User::ROLE_MUNICIPAL_LGU)
            ? AssistantLanguage::text('The Municipal LGU has received :count submitted RBI form(s).', ['count' => $submitted])
            : AssistantLanguage::text('You have :total RBI form(s): :submitted submitted and :drafts draft.', ['total' => $forms->count(), 'submitted' => $submitted, 'drafts' => $drafts]);

        return $this->response(
            $reply.$latestText,
            $user->hasRole(User::ROLE_MUNICIPAL_LGU)
                ? [AssistantLanguage::text('Summarize the municipal registry'), AssistantLanguage::text('Show migration totals')]
                : [AssistantLanguage::text('Summarize our registry'), AssistantLanguage::text('Show migration totals')],
            [[
                'label' => $user->hasRole(User::ROLE_MUNICIPAL_LGU) ? AssistantLanguage::text('Open Municipal Dashboard') : AssistantLanguage::text('Open RBI Forms'),
                'url' => $user->hasRole(User::ROLE_MUNICIPAL_LGU) ? route('dashboard.municipal') : route('barangay.rbi-updates.index'),
            ]]
        );
    }

    private function registry(User $user): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return $this->restricted(AssistantLanguage::text('The Central Registry contains protected barangay records and is available only to authorized staff. You can access only your own account and document requests.'), $user);
        }

        if ($user->hasRole(User::ROLE_BARANGAY)) {
            if (! $user->barangay_id) {
                return $this->missingBarangay($user);
            }

            $barangay = Barangay::withCount(['inhabitants', 'households'])->findOrFail($user->barangay_id);

            return $this->response(
                AssistantLanguage::text('Barangay :name has :residents inhabitant record(s) across :households household(s) in the Central Registry.', ['name' => $barangay->name, 'residents' => $barangay->inhabitants_count, 'households' => $barangay->households_count]),
                [AssistantLanguage::text('Show migration totals'), AssistantLanguage::text('Summarize our RBI forms')],
                [['label' => AssistantLanguage::text('Open Central Registry'), 'url' => route('registry.index')]]
            );
        }

        $barangays = Barangay::where('municipality', Barangay::MUNICIPALITY)
            ->withCount(['inhabitants', 'households'])
            ->get();

        return $this->response(
            AssistantLanguage::text('The municipal registry covers :areas barangay(s), :residents inhabitant record(s), and :households household(s).', ['areas' => $barangays->count(), 'residents' => $barangays->sum('inhabitants_count'), 'households' => $barangays->sum('households_count')]),
            [AssistantLanguage::text('Show migration totals'), AssistantLanguage::text('Summarize submitted RBI forms')],
            [['label' => AssistantLanguage::text('Open Barangay Directory'), 'url' => route('municipal.barangays.index')]]
        );
    }

    private function migration(User $user): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return $this->restricted(AssistantLanguage::text('Migration monitoring contains aggregate staff records and is not available to Resident accounts.'), $user);
        }

        $query = MigrationRecord::query();
        if ($user->hasRole(User::ROLE_BARANGAY)) {
            if (! $user->barangay_id) {
                return $this->missingBarangay($user);
            }
            $query->where('barangay_id', $user->barangay_id);
        }

        $records = $query->get();
        $incoming = $records->where('type', MigrationRecord::TYPE_IN)->count();
        $outgoing = $records->where('type', MigrationRecord::TYPE_OUT)->count();
        $area = $user->hasRole(User::ROLE_BARANGAY) ? 'Barangay '.$user->barangay->name : AssistantLanguage::text('the municipality');

        return $this->response(
            AssistantLanguage::text(':area has :total recorded movement(s): :incoming in-migration and :outgoing out-migration, for a net change of ', ['area' => $area, 'total' => $records->count(), 'incoming' => $incoming, 'outgoing' => $outgoing]).($incoming - $outgoing).'.',
            $user->hasRole(User::ROLE_BARANGAY)
                ? [AssistantLanguage::text('Summarize our registry'), AssistantLanguage::text('Summarize our RBI forms')]
                : [AssistantLanguage::text('Summarize the municipal registry'), AssistantLanguage::text('Summarize submitted RBI forms')],
            [['label' => AssistantLanguage::text('Open Migration Monitoring'), 'url' => route('migration.dashboard')]]
        );
    }

    private function navigation(User $user): array
    {
        return $this->response(
            AssistantLanguage::text('Choose one of the RBIM workspaces available to your account below.'),
            $this->suggestions($user),
            $this->roleActions($user)
        );
    }

    private function restricted(string $message, User $user): array
    {
        return $this->response($message, $this->suggestions($user), $this->dashboardAction($user));
    }

    private function missingBarangay(User $user): array
    {
        return $this->response(
            AssistantLanguage::text('Your staff account is not assigned to a barangay, so I cannot retrieve barangay-scoped records. Ask the Municipal LGU administrator to complete the assignment.'),
            [AssistantLanguage::text('What is my account status?'), AssistantLanguage::text('What can you do?')],
            $this->dashboardAction($user)
        );
    }

    /** @param array<int, string> $needles */
    private function matches(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u';

            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $suggestions
     * @param  array<int, array{label: string, url: string}>  $actions
     * @return array{reply: string, suggestions: array<int, string>, actions: array<int, array{label: string, url: string}>, scope: string}
     */
    private function response(string $reply, array $suggestions, array $actions = []): array
    {
        return [
            'reply' => $reply,
            'suggestions' => $suggestions,
            'actions' => $actions,
            'scope' => AssistantLanguage::text('RBIM system only'),
        ];
    }

    /** @return array<int, string> */
    private function suggestions(User $user): array
    {
        return match ($user->role) {
            User::ROLE_MUNICIPAL_LGU => [AssistantLanguage::text('Summarize submitted RBI forms'), AssistantLanguage::text('Summarize the municipal registry'), AssistantLanguage::text('Show migration totals')],
            User::ROLE_BARANGAY => [AssistantLanguage::text('Show pending resident approvals'), AssistantLanguage::text('Summarize document requests'), AssistantLanguage::text('Summarize our registry')],
            default => [AssistantLanguage::text('Show my document requests'), AssistantLanguage::text('What is my account status?'), AssistantLanguage::text('How do I request a document?')],
        };
    }

    /** @return array<int, array{label: string, url: string}> */
    private function dashboardAction(User $user): array
    {
        return [['label' => AssistantLanguage::text('Open Dashboard'), 'url' => route($this->dashboardRoute($user))]];
    }

    /** @return array<int, array{label: string, url: string}> */
    private function roleActions(User $user): array
    {
        return match ($user->role) {
            User::ROLE_MUNICIPAL_LGU => [
                ['label' => 'Municipal Dashboard', 'url' => route('dashboard.municipal')],
                ['label' => 'Barangay Directory', 'url' => route('municipal.barangays.index')],
                ['label' => 'Migration Monitoring', 'url' => route('migration.dashboard')],
            ],
            User::ROLE_BARANGAY => [
                ['label' => 'Barangay Dashboard', 'url' => route('dashboard.barangay')],
                ['label' => 'Central Registry', 'url' => route('registry.index')],
                ['label' => 'RBI Forms', 'url' => route('barangay.rbi-updates.index')],
            ],
            default => [['label' => 'Resident Portal', 'url' => route('dashboard.resident')]],
        };
    }

    private function dashboardRoute(User $user): string
    {
        return match ($user->role) {
            User::ROLE_MUNICIPAL_LGU => 'dashboard.municipal',
            User::ROLE_BARANGAY => 'dashboard.barangay',
            default => $user->isApproved() ? 'dashboard.resident' : 'approval.pending',
        };
    }
}
