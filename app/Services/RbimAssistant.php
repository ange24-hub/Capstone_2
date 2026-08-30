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
        $message = mb_strtolower($message);

        if ($this->matches($message, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            return $this->response(
                "Hello, {$user->name}. I’m the RBIM Assistant. I can help with the features and records available to your {$user->roleLabel()} account.",
                $this->suggestions($user),
                $this->dashboardAction($user)
            );
        }

        if ($this->matches($message, ['what can you do', 'help', 'options', 'features', 'how can you help'])) {
            return $this->capabilities($user);
        }

        if ($this->matches($message, ['my account', 'my profile', 'who am i', 'account status', 'my barangay'])) {
            $barangay = $user->barangay?->name ? 'Barangay '.$user->barangay->name : 'no assigned barangay';

            return $this->response(
                "You are signed in as {$user->name}, a {$user->roleLabel()}, with {$barangay}. Your account status is {$user->approvalStatusLabel()}.",
                $this->suggestions($user),
                $this->dashboardAction($user)
            );
        }

        if ($this->matches($message, ['document', 'certificate', 'clearance', 'indigency', 'request status'])) {
            return $this->documents($user, $message);
        }

        if ($this->matches($message, ['approval', 'approve', 'pending registration', 'pending account', 'verification'])) {
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
            'I can only help with the RBIM system—accounts, document requests, approvals, registry records, RBI forms, migration monitoring, and the features permitted for your role. I can’t answer topics outside this system.',
            $this->suggestions($user),
            $this->dashboardAction($user)
        );
    }

    private function capabilities(User $user): array
    {
        $reply = match ($user->role) {
            User::ROLE_MUNICIPAL_LGU => 'I can summarize submitted RBI forms, municipality-wide registry and migration totals, pending secretary approvals, and guide you to municipal dashboards.',
            User::ROLE_BARANGAY => 'I can summarize your barangay registry, resident approvals, document requests, RBI forms, and migration records, and guide you to the correct workspace.',
            default => 'I can explain resident services, show the status of your own document requests, confirm your account details, and guide you around the Resident Portal.',
        };

        return $this->response($reply, $this->suggestions($user), $this->dashboardAction($user));
    }

    private function documents(User $user, string $message): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            $requests = $user->documentRequests()->latest()->get();
            $pending = $requests->whereIn('status', [DocumentRequest::STATUS_PENDING, DocumentRequest::STATUS_PROCESSING])->count();
            $ready = $requests->where('status', DocumentRequest::STATUS_READY)->count();
            $latest = $requests->first();
            $latestText = $latest
                ? " Your latest request is {$latest->reference_number} ({$latest->typeLabel()}), currently {$latest->statusLabel()}.".$this->residentPaymentSummary($latest)
                : ' You have not submitted a document request yet.';

            return $this->response(
                "You have {$requests->count()} document request(s): {$pending} pending or processing and {$ready} ready for release.".$latestText.' Use the Resident Portal form to request a barangay document.',
                ['How do I request a document?', 'What is my account status?'],
                [['label' => 'Open Resident Portal', 'url' => route('dashboard.resident')]]
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
                "Barangay {$user->barangay->name} has {$requests->count()} document request(s): {$pending} pending review, {$processing} processing, and {$ready} ready for release. Process them in the Barangay Dashboard.",
                ['Show pending resident approvals', 'Summarize our registry'],
                [['label' => 'Open Document Requests', 'url' => route('dashboard.barangay').'#document-requests-title']]
            );
        }

        return $this->response(
            'Resident document requests are processed by their assigned barangay. Municipal accounts do not open individual resident requests in this RBIM workspace.',
            ['Summarize submitted RBI forms', 'Show pending secretary approvals'],
            $this->dashboardAction($user)
        );
    }

    private function residentPaymentSummary(DocumentRequest $request): string
    {
        if (! $request->requiresPayment()) {
            return ' No payment is required.';
        }

        return ' The GCash fee is ₱'.number_format((float) $request->amount_due, 2).' and the payment status is '.$request->paymentStatusLabel().'.';
    }

    private function approvals(User $user): array
    {
        if ($user->hasRole(User::ROLE_MUNICIPAL_LGU)) {
            $count = User::where('role', User::ROLE_BARANGAY)
                ->where('approval_status', User::APPROVAL_PENDING)
                ->count();

            return $this->response(
                "There are {$count} barangay secretary account(s) awaiting Municipal LGU verification.",
                ['Summarize submitted RBI forms', 'Summarize the municipal registry'],
                [['label' => 'Review Secretary Accounts', 'url' => route('dashboard.municipal').'#secretary-approvals']]
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
                "Barangay {$user->barangay->name} has {$count} resident registration(s) awaiting verification.",
                ['Summarize document requests', 'Summarize our registry'],
                [['label' => 'Review Resident Accounts', 'url' => route('dashboard.barangay').'#resident-approvals-title']]
            );
        }

        return $this->response(
            "Your resident account status is {$user->approvalStatusLabel()}.".($user->isApproved() ? ' You can use the Resident Portal.' : ' Your assigned barangay must verify the account before portal access is enabled.'),
            ['Show my document requests', 'What can you do?'],
            $this->dashboardAction($user)
        );
    }

    private function rbi(User $user): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return $this->restricted('RBI forms are staff records and are not available to Resident accounts.', $user);
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
            ? ' The latest is '.($latest->barangay_name ?: 'an unassigned barangay').' for '.($latest->reporting_month?->format('F Y') ?: 'an unset month').'.'
            : ' No RBI form is available yet.';

        $reply = $user->hasRole(User::ROLE_MUNICIPAL_LGU)
            ? "The Municipal LGU has received {$submitted} submitted RBI form(s)."
            : "You have {$forms->count()} RBI form(s): {$submitted} submitted and {$drafts} draft.";

        return $this->response(
            $reply.$latestText,
            $user->hasRole(User::ROLE_MUNICIPAL_LGU)
                ? ['Summarize the municipal registry', 'Show migration totals']
                : ['Summarize our registry', 'Show migration totals'],
            [[
                'label' => $user->hasRole(User::ROLE_MUNICIPAL_LGU) ? 'Open Municipal Dashboard' : 'Open RBI Forms',
                'url' => $user->hasRole(User::ROLE_MUNICIPAL_LGU) ? route('dashboard.municipal') : route('barangay.rbi-updates.index'),
            ]]
        );
    }

    private function registry(User $user): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return $this->restricted('The Central Registry contains protected barangay records and is available only to authorized staff. You can access only your own account and document requests.', $user);
        }

        if ($user->hasRole(User::ROLE_BARANGAY)) {
            if (! $user->barangay_id) {
                return $this->missingBarangay($user);
            }

            $barangay = Barangay::withCount(['inhabitants', 'households'])->findOrFail($user->barangay_id);

            return $this->response(
                "Barangay {$barangay->name} has {$barangay->inhabitants_count} inhabitant record(s) across {$barangay->households_count} household(s) in the Central Registry.",
                ['Show migration totals', 'Summarize our RBI forms'],
                [['label' => 'Open Central Registry', 'url' => route('registry.index')]]
            );
        }

        $barangays = Barangay::where('municipality', Barangay::MUNICIPALITY)
            ->withCount(['inhabitants', 'households'])
            ->get();

        return $this->response(
            "The municipal registry covers {$barangays->count()} barangay(s), {$barangays->sum('inhabitants_count')} inhabitant record(s), and {$barangays->sum('households_count')} household(s).",
            ['Show migration totals', 'Summarize submitted RBI forms'],
            [['label' => 'Open Barangay Directory', 'url' => route('municipal.barangays.index')]]
        );
    }

    private function migration(User $user): array
    {
        if ($user->hasRole(User::ROLE_RESIDENT)) {
            return $this->restricted('Migration monitoring contains aggregate staff records and is not available to Resident accounts.', $user);
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
        $area = $user->hasRole(User::ROLE_BARANGAY) ? 'Barangay '.$user->barangay->name : 'the municipality';

        return $this->response(
            "{$area} has {$records->count()} recorded movement(s): {$incoming} in-migration and {$outgoing} out-migration, for a net change of ".($incoming - $outgoing).'.',
            $user->hasRole(User::ROLE_BARANGAY)
                ? ['Summarize our registry', 'Summarize our RBI forms']
                : ['Summarize the municipal registry', 'Summarize submitted RBI forms'],
            [['label' => 'Open Migration Monitoring', 'url' => route('migration.dashboard')]]
        );
    }

    private function navigation(User $user): array
    {
        return $this->response(
            'Choose one of the RBIM workspaces available to your account below.',
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
            'Your staff account is not assigned to a barangay, so I cannot retrieve barangay-scoped records. Ask the Municipal LGU administrator to complete the assignment.',
            ['What is my account status?', 'What can you do?'],
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
            'scope' => 'RBIM system only',
        ];
    }

    /** @return array<int, string> */
    private function suggestions(User $user): array
    {
        return match ($user->role) {
            User::ROLE_MUNICIPAL_LGU => ['Summarize submitted RBI forms', 'Summarize the municipal registry', 'Show migration totals'],
            User::ROLE_BARANGAY => ['Show pending resident approvals', 'Summarize document requests', 'Summarize our registry'],
            default => ['Show my document requests', 'What is my account status?', 'How do I request a document?'],
        };
    }

    /** @return array<int, array{label: string, url: string}> */
    private function dashboardAction(User $user): array
    {
        return [['label' => 'Open Dashboard', 'url' => route($this->dashboardRoute($user))]];
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
