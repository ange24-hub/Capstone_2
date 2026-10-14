<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;

class AssistantGuide
{
    public static function questions(User $user): array
    {
        return array_values(array_unique(array_merge(...array_column(self::questionGroups($user), 'questions'))));
    }

    public static function questionGroups(User $user): array
    {
        return [['label' => AssistantLanguage::text('Quick questions'), 'questions' => self::summaryQuestions($user)],
            ...AssistantKnowledgeBase::questionGroups($user)];
    }

    private static function summaryQuestions(User $user): array
    {
        $common = [AssistantLanguage::text('What can I ask?'), AssistantLanguage::text('Open resident concerns'), 'Unsaon pag request ug certificate?', 'Unsaon pagbayad sa GCash?', 'Pila ang document fees?', AssistantLanguage::text('What do request statuses mean?'), 'Unsaon pag change sa password?'];
        if ($user->hasRole(User::ROLE_RESIDENT)) return array_merge([AssistantLanguage::text('Show my document requests'), 'Ready na ba akong request?', AssistantLanguage::text('What is my payment status?'), AssistantLanguage::text('What is my account status?')], $common);
        if ($user->hasRole(User::ROLE_BARANGAY)) $common = array_merge(['Pila ang pending document requests?', AssistantLanguage::text('Show ready document requests'), AssistantLanguage::text('Show payment status counts')], $common);
        $common[] = AssistantLanguage::text('Open migration trends');
        $common[] = AssistantLanguage::text('Show PWD names');
        $common[] = AssistantLanguage::text('Show senior citizen names');

        return array_merge(['Pila ka tawo sa among barangay?', 'Pila ka pamilya ug household?', 'Pila ka lalaki ug babaye?', 'Pila ka senior citizen ug PWD?', 'Pila ka bata?', AssistantLanguage::text('Show migration this month'), AssistantLanguage::text('Show migration last month'), AssistantLanguage::text('Which month has the most migration?'), AssistantLanguage::text('Show population coverage'), AssistantLanguage::text('Summarize submitted RBI forms'), AssistantLanguage::text('Show pending approvals')], $common);
    }

    public function respond(User $user, string $question): ?array
    {
        $q = mb_strtolower($question);
        $how = ! preg_match('/\bhow (many|much)\b/u', $q) && preg_match('/\b(how|unsaon|how do|how to|explain|pasabut|meaning|mean|rules)\b/u', $q);
        $reply = null;
        $actions = [];
        if (preg_match('/\b(concerns?|reklamo|complaints?)\b/u', $q)) {
            $municipal = $user->hasRole(User::ROLE_MUNICIPAL_LGU);
            $permitted = $municipal || ($user->isApproved() && $user->barangay_id);
            return ['reply' => AssistantLanguage::text('Residents can submit a service concern with an optional photo or PDF, track its status, and add information through My Concerns. Barangay staff review concerns, record public updates, and keep separate staff-only notes. Local AI can suggest a category for staff review; it does not decide urgency or resolution. Municipal Concern Insights shows aggregate counts. This inbox is not for emergency response.'),
                'scope' => AssistantLanguage::text('RBIM system only'), 'suggestions' => ['What can I ask?'],
                'actions' => $permitted ? [['label' => $municipal ? AssistantLanguage::text('Concern Insights') : AssistantLanguage::text('Open Concerns'), 'url' => route($municipal ? 'concerns.summary' : 'concerns.index')]] : []];
        }
        if (preg_match('/\b(forecast readiness|ready for forecasting|migration trends)\b/u', $q)) {
            return ['reply' => AssistantLanguage::text('Open Migration Trends to review recorded movements and the provisional next-month out-migration estimate when recent records are available. Missing records are not verified zero movement.'),
                'scope' => AssistantLanguage::text('RBIM system only'), 'suggestions' => ['What can I ask?'],
                'actions' => $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]) && $user->isApproved() ? [['label' => AssistantLanguage::text('Open Migration Trends'), 'url' => route('migration.dashboard')]] : []];
        }
        if (preg_match('/\b(what can i ask|what can you do|help|unsa.*mapangutana|sample questions)\b/u', $q)) {
            $reply = AssistantLanguage::text("You can ask in Cebuano or English. Try a complete question and include the barangay or reporting month when needed.\n\n")
                .implode("\n", array_map(fn ($sample) => '- '.$sample, array_slice(self::summaryQuestions($user), 0, 4)))
                .AssistantLanguage::text("\n\nOpen Explore questions to browse all topics by category.")
                .AssistantLanguage::text("\n\nAnswers follow your account permissions. I can explain records and guide you; I cannot edit records or approve requests through chat.");
        } elseif ($how && preg_match('/\b(password|profile|email)\b/u', $q)) {
            if ($user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU])) {
                $reply = AssistantLanguage::text('Open My Profile to update your name or email, or set a new password. Enter your current password when changing email or password; leave the new password blank to keep it.');
                $actions[] = ['label' => AssistantLanguage::text('My Profile'), 'url' => route('profile.edit')];
            } else {
                $reply = AssistantLanguage::text('Resident accounts do not have a My Profile editor in this system. To reset your password, sign out and use Forgot Password on the login page with your registered email. Contact your barangay staff to review other account changes.');
            }
        } elseif (preg_match('/\b(fees|fee|presyo|bayranan)\b/u', $q)) {
            $reply = AssistantLanguage::text('Barangay Indigency is free. The secretary sets the fee for Barangay Clearance, Certificate of Residency, and Business Clearance after you submit a request. Check My Requests for the amount due, then pay through official GCash or in person before release.');
        } elseif (preg_match('/\b(gcash|pagbayad|payment help)\b/u', $q)) {
            $reply = AssistantLanguage::text('Wait for the secretary to set your fee in My Requests. You can pay through the official GCash details and submit the reference and receipt, or choose in-person payment and pay at the barangay office. The secretary must verify the GCash payment or confirm receipt in person before release. Barangay Indigency is free.');
        } elseif ($how && preg_match('/\b(status|statuses)\b/u', $q)) {
            $reply = AssistantLanguage::text("Document request statuses:\nPending review: awaiting barangay review.\nProcessing: being prepared.\nReady for release: ready for collection.\nCompleted: marked completed by staff.\nRejected: check the staff remarks.\nPayment verification is a separate status; it does not mean the document is ready.");
        } elseif ($how && preg_match('/\b(document|documents|certificate|clearance|indigency|request)\b/u', $q)) {
            $reply = AssistantLanguage::text('An approved resident can open Document Requests, choose the document type, enter the purpose and other required fields, then submit. Track progress in My Requests and follow payment instructions if an amount is due. Contact your barangay for requirements or release arrangements not shown in the system.');
            if ($user->hasRole(User::ROLE_RESIDENT) && $user->isApproved()) $actions[] = ['label' => AssistantLanguage::text('Request a document'), 'url' => route('resident.document-requests.create')];
        } elseif ($how && preg_match('/\b(family|families|pamilya|household)\b/u', $q)) {
            $reply = AssistantLanguage::text('Families and households are different counts. Decimal family numbers identify separate families within the same base household. A person living alone in a household is also counted as one family. Missing family numbers can make the identified family count incomplete; the report shows this limitation.');
        } elseif ($how && preg_match('/\b(pwd|senior|sc)\b/u', $q)) {
            $reply = AssistantLanguage::text('Population reports count senior citizens from valid birth dates (age 60 or above) or recognized senior-citizen remarks, without counting the same record twice. PWD counts use recognized PWD remarks. A missing marker does not establish that a person has no disability.');
        } elseif ($how && preg_match('/\b(migration|migrasyon|net change)\b/u', $q)) {
            $reply = AssistantLanguage::text('Migration reports summarize recorded move-in and move-out events by movement date. Net change is in-migration minus out-migration. These are event counts, not necessarily unique people. A temporary stay for work or school should not automatically be treated as a recorded transfer; barangay staff must verify the residence information before encoding a movement.');
        } elseif ($how && preg_match('/\b(pdf|download|report)\b/u', $q)) {
            $reply = AssistantLanguage::text('Authorized barangay and municipal staff can open Population Reports or Migration Reports, select the available filters, and choose Download PDF. The PDF uses stored records. Ask for a population or migration summary here to get the report links.');
        }
        if ($reply === null) return null;

        return ['reply' => $reply, 'scope' => AssistantLanguage::text('RBIM system only'), 'suggestions' => array_slice(self::questions($user), 0, 3), 'actions' => $actions];
    }
}
