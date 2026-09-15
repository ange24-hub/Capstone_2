<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;

class AssistantGuide
{
    public static function questions(User $user): array
    {
        $common = ['What can I ask?', 'Open resident concerns', 'Unsaon pag request ug certificate?', 'Unsaon pagbayad sa GCash?', 'Pila ang document fees?', 'What do request statuses mean?', 'Unsaon pag change sa password?'];
        if ($user->hasRole(User::ROLE_RESIDENT)) return array_merge(['Show my document requests', 'Ready na ba akong request?', 'What is my payment status?', 'What is my account status?'], $common);
        if ($user->hasRole(User::ROLE_BARANGAY)) $common = array_merge(['Pila ang pending document requests?', 'Show ready document requests', 'Show payment status counts'], $common);
        $common[] = 'Open forecast readiness';

        return array_merge(['Pila ka tawo sa among barangay?', 'Pila ka pamilya ug household?', 'Pila ka lalaki ug babaye?', 'Pila ka senior citizen ug PWD?', 'Pila ka bata?', 'Show migration this month', 'Show migration last month', 'Which month has the most migration?', 'Show population coverage', 'Summarize submitted RBI forms', 'Show pending approvals'], $common);
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
            return ['reply' => 'Residents can submit a service concern with an optional photo or PDF, track its status, and add information through My Concerns. Barangay staff review concerns, record public updates, and keep separate staff-only notes. Local AI can suggest a category for staff review; it does not decide urgency or resolution. Municipal Concern Insights shows aggregate counts. This inbox is not for emergency response.',
                'scope' => 'RBIM system only', 'suggestions' => ['What can I ask?'],
                'actions' => $permitted ? [['label' => $municipal ? 'Concern Insights' : 'Open Concerns', 'url' => route($municipal ? 'concerns.summary' : 'concerns.index')]] : []];
        }
        if (preg_match('/\b(forecast readiness|ready for forecasting)\b/u', $q)) {
            return ['reply' => 'Forecast Readiness checks 24 completed months of recorded migration and submitted RBI history. Missing records are not verified zeros. Forecasting remains unavailable until reporting completeness and model validation are established.',
                'scope' => 'RBIM system only', 'suggestions' => ['What can I ask?'],
                'actions' => $user->hasAnyRole([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU]) && $user->isApproved() ? [['label' => 'Open Forecast Readiness', 'url' => route('analysis.forecast-readiness')]] : []];
        }
        if (preg_match('/\b(what can i ask|what can you do|help|unsa.*mapangutana|sample questions)\b/u', $q)) {
            $reply = "You can ask in Cebuano or English. Try a complete question and include the barangay or reporting month when needed.\n\n".implode("\n", self::questions($user))."\n\nAnswers follow your account permissions. I can explain records and guide you; I cannot edit records or approve requests through chat.";
        } elseif ($how && preg_match('/\b(password|profile|email)\b/u', $q)) {
            $reply = 'Open My Profile to update your name or email, or set a new password. Enter your current password when changing email or password; leave the new password blank to keep it.';
            $actions[] = ['label' => 'My Profile', 'url' => route('profile.edit')];
        } elseif (preg_match('/\b(fees|fee|presyo|bayranan)\b/u', $q)) {
            $reply = "Current configured document fees:\n".collect(DocumentRequest::typeLabels())->map(fn ($label, $type) => $label.': PHP '.number_format(DocumentRequest::feeFor($type), 2))->implode("\n")."\nFor an existing request, check its amount due; it may differ from the current configured fee.";
        } elseif (preg_match('/\b(gcash|pagbayad|payment help)\b/u', $q)) {
            $reply = 'Open your document request and check its amount due and the barangay payment instructions. If payment is required, use the displayed GCash details and submit the payment reference and proof through the request form. Barangay staff verify the payment. Uploading proof does not automatically mark it as paid. If no payment is required, no GCash payment is needed.';
        } elseif ($how && preg_match('/\b(status|statuses)\b/u', $q)) {
            $reply = "Document request statuses:\nPending review: awaiting barangay review.\nProcessing: being prepared.\nReady for release: ready for collection.\nCompleted: marked completed by staff.\nRejected: check the staff remarks.\nPayment verification is a separate status; it does not mean the document is ready.";
        } elseif ($how && preg_match('/\b(document|documents|certificate|clearance|indigency|request)\b/u', $q)) {
            $reply = 'An approved resident can open Document Requests, choose the document type, enter the purpose and other required fields, then submit. Track progress in My Requests and follow payment instructions if an amount is due. Contact your barangay for requirements or release arrangements not shown in the system.';
            if ($user->hasRole(User::ROLE_RESIDENT) && $user->isApproved()) $actions[] = ['label' => 'Request a document', 'url' => route('resident.document-requests.create')];
        } elseif ($how && preg_match('/\b(family|families|pamilya|household)\b/u', $q)) {
            $reply = 'Families and households are different counts. Decimal family numbers identify separate families within the same base household. A person living alone in a household is also counted as one family. Missing family numbers can make the identified family count incomplete; the report shows this limitation.';
        } elseif ($how && preg_match('/\b(pwd|senior|sc)\b/u', $q)) {
            $reply = 'Population reports count senior citizens from valid birth dates (age 60 or above) or recognized senior-citizen remarks, without counting the same record twice. PWD counts use recognized PWD remarks. A missing marker does not establish that a person has no disability.';
        } elseif ($how && preg_match('/\b(migration|migrasyon|net change)\b/u', $q)) {
            $reply = 'Migration reports summarize recorded move-in and move-out events by movement date. Net change is in-migration minus out-migration. These are event counts, not necessarily unique people. A temporary stay for work or school should not automatically be treated as a recorded transfer; barangay staff must verify the residence information before encoding a movement.';
        } elseif ($how && preg_match('/\b(pdf|download|report)\b/u', $q)) {
            $reply = 'Authorized barangay and municipal staff can open Population Reports or Migration Reports, select the available filters, and choose Download PDF. The PDF uses stored records. Ask for a population or migration summary here to get the report links.';
        }
        if ($reply === null) return null;

        return ['reply' => $reply, 'scope' => 'RBIM system only', 'suggestions' => array_slice(self::questions($user), 0, 3), 'actions' => $actions];
    }
}
