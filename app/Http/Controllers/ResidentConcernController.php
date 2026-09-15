<?php

namespace App\Http\Controllers;

use App\Models\{Barangay, ResidentConcern, User};
use App\Services\LocalConcernClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class ResidentConcernController extends Controller
{
    private function checkAccess(Request $request, ?ResidentConcern $concern = null, bool $staffOnly = false): void
    {
        $user = $request->user();
        abort_unless($user->isApproved() && $user->barangay_id && $user->hasAnyRole([User::ROLE_RESIDENT, User::ROLE_BARANGAY]), 403);
        if ($staffOnly) abort_unless($user->hasRole(User::ROLE_BARANGAY), 403);
        if ($concern) {
            abort_unless((int) $concern->barangay_id === (int) $user->barangay_id, 403);
            if ($user->hasRole(User::ROLE_RESIDENT)) abort_unless((int) $concern->resident_id === (int) $user->id, 403);
        }
    }

    public function index(Request $request)
    {
        $this->checkAccess($request);
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(ResidentConcern::statuses()))],
            'category' => ['nullable', Rule::in(array_keys(ResidentConcern::categories()))], 'search' => ['nullable', 'string', 'max:160']]);
        $staff = $request->user()->hasRole(User::ROLE_BARANGAY);
        $query = ResidentConcern::where('barangay_id', $request->user()->barangay_id)
            ->when(! $staff, fn ($q) => $q->where('resident_id', $request->user()->id));
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $concerns = $query->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$value.'%')->orWhere('title', 'like', '%'.$value.'%')))
            ->latest('id')->paginate(15)->withQueryString();
        return view('concerns.index', compact('concerns', 'staff', 'counts'));
    }

    public function create(Request $request)
    {
        $this->checkAccess($request);
        abort_unless($request->user()->hasRole(User::ROLE_RESIDENT), 403);
        return view('concerns.create');
    }

    public function store(Request $request)
    {
        $this->checkAccess($request);
        abort_unless($request->user()->hasRole(User::ROLE_RESIDENT), 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:4000'],
            'category' => ['required', Rule::in(array_keys(ResidentConcern::categories()))],
            'location' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $path = null;
        try {
            if ($file = $request->file('attachment')) {
                $path = $file->store('concern-attachments', 'local');
                abort_unless($path, 500, 'The attachment could not be stored. Please try again.');
            }
            $concern = DB::transaction(function () use ($data, $request, $path) {
                $concern = ResidentConcern::create([
                    'reference' => 'CON-'.Str::ulid(), 'resident_id' => $request->user()->id,
                    'barangay_id' => $request->user()->barangay_id, 'title' => $data['title'],
                    'description' => $data['description'], 'category' => $data['category'],
                    'location' => $data['location'] ?? null, 'status' => 'submitted',
                    'attachment_path' => $path, 'attachment_name' => $path ? 'supporting-file.'.$request->file('attachment')->extension() : null,
                ]);
                $concern->updates()->create(['author_id' => $request->user()->id, 'status' => 'submitted', 'category' => $data['category'], 'message' => 'Concern submitted to the barangay.']);
                return $concern;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($request->routeIs('api.resident.*')) {
            return response()->json(['message' => 'Concern submitted.', 'id' => $concern->id, 'reference' => $concern->reference], 201);
        }
        return redirect()->route('concerns.show', $concern)->with('status', 'Concern submitted. Keep your reference number to track updates.');
    }

    public function show(Request $request, ResidentConcern $concern)
    {
        $this->checkAccess($request, $concern);
        $staff = $request->user()->hasRole(User::ROLE_BARANGAY);
        $concern->load(['barangay:id,name', 'resident:id,name']);
        $columns = ['id', 'resident_concern_id', 'author_id', 'status', 'category', 'message', 'created_at'];
        if ($staff) $columns[] = 'internal_note';
        $updates = $concern->updates()->select($columns)->with('author:id,name,role')->orderByDesc('id')->paginate(20);
        return view('concerns.show', compact('concern', 'staff', 'updates'));
    }

    public function update(Request $request, ResidentConcern $concern)
    {
        $this->checkAccess($request, $concern, true);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ResidentConcern::statuses()))],
            'category' => ['required', Rule::in(array_keys(ResidentConcern::categories()))],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'], 'version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $concern, $data) {
            $locked = ResidentConcern::lockForUpdate()->findOrFail($concern->id);
            $this->checkAccess($request, $locked, true);
            if ($locked->version !== (int) $data['version']) throw ValidationException::withMessages(['version' => 'This concern has a newer update. Reload the page before saving.']);
            if (! array_key_exists($data['status'], $locked->allowedStatuses())) throw ValidationException::withMessages(['status' => 'Review the concern before advancing its status.']);
            $locked->update(['status' => $data['status'], 'category' => $data['category'], 'version' => $locked->version + 1]);
            $locked->updates()->create(['author_id' => $request->user()->id, 'status' => $data['status'], 'category' => $data['category'], 'message' => $data['message'], 'internal_note' => $data['internal_note'] ?? null]);
        });
        return back()->with('status', 'Concern updated. The resident can see your public message.');
    }

    public function reply(Request $request, ResidentConcern $concern)
    {
        $this->checkAccess($request, $concern);
        abort_unless($request->user()->hasRole(User::ROLE_RESIDENT), 403);
        $data = $request->validate(['message' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $concern, $data) {
            $locked = ResidentConcern::lockForUpdate()->findOrFail($concern->id);
            $this->checkAccess($request, $locked);
            if ($locked->status === 'closed') throw ValidationException::withMessages(['message' => 'This concern is closed. Contact the barangay to reopen it or submit a new concern.']);
            $locked->updates()->create(['author_id' => $request->user()->id, 'message' => $data['message']]);
            $locked->update(['version' => $locked->version + 1]);
        });
        if ($request->routeIs('api.resident.*')) {
            return response()->json(['message' => 'Additional information sent to the barangay.']);
        }
        return back()->with('status', 'Additional information sent to the barangay.');
    }

    public function attachment(Request $request, ResidentConcern $concern)
    {
        $this->checkAccess($request, $concern);
        abort_unless($concern->attachment_path && Storage::disk('local')->exists($concern->attachment_path), 404);
        return Storage::disk('local')->download($concern->attachment_path, $concern->attachment_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function suggest(Request $request, ResidentConcern $concern, LocalConcernClassifier $classifier)
    {
        $this->checkAccess($request, $concern, true);
        $suggestion = $classifier->suggest($concern->title, $concern->description);
        if (! $suggestion) return back()->withErrors(['ai' => 'Local AI is unavailable or returned an invalid category. Select the category manually; your concern is unchanged.']);
        $updated = ResidentConcern::whereKey($concern->id)->where('version', $concern->version)->update([
            'ai_category' => $suggestion['category'], 'ai_model' => $suggestion['model'], 'ai_suggested_at' => now(), 'version' => $concern->version + 1,
        ]);
        if (! $updated) return back()->withErrors(['ai' => 'The concern changed while AI was running. Reload and try again.']);
        return back()->with('status', 'Local AI suggestion ready. Review it before changing the category.');
    }

    public function summary(Request $request)
    {
        abort_unless($request->user()->hasRole(User::ROLE_MUNICIPAL_LGU), 403);
        $filters = $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id'], 'days' => ['nullable', 'integer', 'in:30,90,365']]);
        $days = (int) ($filters['days'] ?? 30);
        $start = now()->startOfDay()->subDays($days - 1);
        $rows = ResidentConcern::whereBetween('created_at', [$start, now()])
            ->when($filters['barangay_id'] ?? null, fn ($q, $id) => $q->where('barangay_id', $id))
            ->selectRaw('barangay_id, category, status, COUNT(*) as total')->groupBy('barangay_id', 'category', 'status')->get();
        $barangays = Barangay::orderBy('name')->get(['id', 'name']);
        return view('concerns.summary', compact('rows', 'barangays', 'days', 'start'));
    }
}
