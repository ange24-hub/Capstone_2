<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Barangay, DocumentRequest, ResidentConcern, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, Rules\Password, ValidationException};

class ResidentMobileController extends Controller
{
    public function barangays()
    {
        return response()->json(['data' => Barangay::where('municipality', Barangay::MUNICIPALITY)->orderBy('name')->get(['id', 'name'])]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
        $user = User::where('email', Str::lower(trim($data['email'])))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        abort_unless($user->hasRole(User::ROLE_RESIDENT), 403, 'This app is for resident accounts only. Staff should use the website.');
        abort_if($user->approval_status === User::APPROVAL_REJECTED, 403, 'Your registration was rejected. Contact your barangay office.');
        return $this->session($user);
    }

    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('municipality', Barangay::MUNICIPALITY)],
            'password' => ['required', 'string', 'max:1024', 'confirmed', Password::defaults()],
        ]);
        return DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'role' => User::ROLE_RESIDENT, 'approval_status' => User::APPROVAL_PENDING]);
            return $this->session($user, 201);
        });
    }

    private function session(User $user, int $status = 200)
    {
        $expires = now()->addDays(7);
        return response()->json(['token' => $user->createToken('resident-android', ['resident:mobile'], $expires)->plainTextToken,
            'expires_at' => $expires->toIso8601String(), 'user' => $this->userData($user)], $status)
            ->header('Cache-Control', 'private, no-store');
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->role, 'approval_status' => $user->approval_status,
            'barangay' => $user->barangay?->only(['id', 'name'])];
    }

    public function me(Request $request) { return response()->json(['user' => $this->userData($request->user())]); }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Signed out.']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required', 'string'], 'password' => ['nullable', 'string', 'max:1024', 'confirmed', Password::defaults()]]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }
        DB::transaction(function () use ($user, $data) {
            $changes = ['email' => $data['email']];
            if ($data['email'] !== $user->email) $changes['email_verified_at'] = null;
            if (! empty($data['password'])) $changes['password'] = Hash::make($data['password']);
            $user->forceFill($changes)->save();
            $user->tokens()->delete();
        });
        return response()->json(['message' => 'Account updated. Sign in again on your devices.']);
    }

    public function catalog(Request $request)
    {
        return response()->json(['document_types' => collect(DocumentRequest::typeLabels())->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'fee' => DocumentRequest::feeFor($key),
            'available' => DocumentRequest::feeFor($key) <= 0 || $request->user()->barangay->gcashIsReady(),
        ])->values(), 'categories' => collect(ResidentConcern::categories())->map(fn ($label, $key) => compact('key', 'label'))->values()]);
    }

    public function dashboard(Request $request)
    {
        $documents = $this->documentsQuery($request);
        $concerns = $this->concernsQuery($request);
        return response()->json(['user' => $this->userData($request->user()), 'summary' => [
            'documents' => (clone $documents)->count(),
            'ready' => (clone $documents)->where('status', DocumentRequest::STATUS_READY)->count(),
            'active_concerns' => (clone $concerns)->whereNotIn('status', ['resolved', 'closed'])->count(),
        ]]);
    }

    private function documentsQuery(Request $request)
    {
        return $request->user()->documentRequests()->where('barangay_id', $request->user()->barangay_id);
    }

    private function concernsQuery(Request $request)
    {
        return ResidentConcern::where('resident_id', $request->user()->id)->where('barangay_id', $request->user()->barangay_id);
    }

    private function documentData(DocumentRequest $document): array
    {
        return [...$document->only(['id', 'reference_number', 'purpose', 'status', 'amount_due', 'payment_status',
            'payment_reference', 'payment_remarks', 'remarks', 'created_at']), 'type_label' => $document->typeLabel(),
            'status_label' => $document->statusLabel(), 'payment_status_label' => $document->paymentStatusLabel()];
    }

    public function documents(Request $request)
    {
        return response()->json($this->documentsQuery($request)->latest('id')->paginate(20)->through(fn ($d) => $this->documentData($d)));
    }

    public function document(Request $request, int $id)
    {
        $document = $this->documentsQuery($request)->findOrFail($id);
        $barangay = $request->user()->barangay;
        return response()->json(['data' => $this->documentData($document), 'payment' => $barangay->gcashIsReady() ? [
            'merchant' => $barangay->gcash_merchant_name, 'account' => $barangay->gcash_account_identifier,
        ] : null]);
    }

    public function paymentQr(Request $request)
    {
        $barangay = $request->user()->barangay;
        abort_unless($barangay->gcashIsReady() && Storage::disk('local')->exists($barangay->gcash_qr_path), 404);
        return Storage::disk('local')->response($barangay->gcash_qr_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function concernData(ResidentConcern $concern): array
    {
        return [...$concern->only(['id', 'reference', 'title', 'description', 'location', 'status', 'category', 'created_at']),
            'status_label' => ResidentConcern::statuses()[$concern->status] ?? $concern->status,
            'category_label' => ResidentConcern::categories()[$concern->category] ?? $concern->category];
    }

    public function concerns(Request $request)
    {
        return response()->json($this->concernsQuery($request)->latest('id')->paginate(20)->through(fn ($c) => $this->concernData($c)));
    }

    public function concern(Request $request, int $id)
    {
        $concern = $this->concernsQuery($request)->findOrFail($id);
        $updates = $concern->updates()->latest('id')->paginate(20, ['id', 'resident_concern_id', 'status', 'category', 'message', 'created_at']);
        return response()->json(['data' => $this->concernData($concern), 'updates' => $updates]);
    }
}
