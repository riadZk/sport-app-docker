<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Team\CreateTeamRequest;
use App\Http\Requests\Team\UpdateTeamLogoRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\DefaultLogo;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BaseNotificationService;
use Illuminate\Http\Request;

class TeamController extends ApiController
{
    public function __construct(
        protected BaseNotificationService $notifier,
    ) {}

    /**
     * List all teams owned by the authenticated user.
     */
    public function index(Request $request)
    {
        $teams = $request->user()
            ->teams()
            ->get();

        return $this->jsonResponse($teams, 'Teams retrieved successfully');
    }

    /**
     * List all selectable default team logos.
     */
    public function defaultLogos()
    {
        $logos = DefaultLogo::with('media')->get()->map(fn($logo) => [
            'id'       => $logo->id,
            'name'     => $logo->name,
            'logo_url' => $logo->logo_url,
        ]);

        return $this->jsonResponse($logos, 'Default logos retrieved successfully');
    }

    /**
     * Create a new team for the authenticated user.
     */
    public function store(CreateTeamRequest $request)
    {
        $data = $request->safe()->except('logo');

        $data['user_id'] = $request->user()->id;

        if ($data['logo_type'] === 'upload') {
            $data['default_logo_id'] = null;
        }

        

        $team = Team::create($data);

        if ($request->logo_type === 'upload' && $request->hasFile('logo')) {
            $team->addMediaFromRequest('logo')
                ->usingFileName('team_logo_' . $team->id . '_' . now()->timestamp . '.' . $request->file('logo')->getClientOriginalExtension())
                ->toMediaCollection('logo');
        }

        // Add the creator as captain in TeamMember
        $playerStat = $request->user()
            ->playerStats()
            ->where('sport_category_id', $team->sport_category_id)
            ->first();

        if ($playerStat) {
            TeamMember::create([
                'player_id'  => $playerStat->id,
                'team_id'    => $team->id,
                'role'       => 'captain',
                'status'     => 'active',
                'start_date' => now()->toDateString(),
            ]);
        }

        // Send invitations to players provided during team creation
        if ($request->filled('invitation_players')) {
            $this->notifier->sendBulkInvitations(
                $team,
                $request->user()->id,
                $request->invitation_players,
                $request->invitation_message,
            );
        }

        return $this->jsonResponse($team, 'Team created successfully', 201);
    }

    /**
     * Show a single team.
     */
    public function show(Request $request, Team $team)
    {
        return $this->jsonResponse($team, 'Team retrieved successfully');
    }

    /**
     * Update the details of an existing team (excluding logo).
     */
    public function update(UpdateTeamRequest $request, Team $team)
    {
        $this->authorizeTeamOwner($request, $team);

        $team->update($request->safe()->all());

        return $this->jsonResponse($team, 'Team updated successfully');
    }

    /**
     * Update the logo of an existing team.
     * Supports switching between an uploaded image and a default logo.
     */
    public function updateLogo(UpdateTeamLogoRequest $request, Team $team)
    {
        $this->authorizeTeamOwner($request, $team);

        if ($request->logo_type === 'upload') {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            $team->clearMediaCollection('logo');
            $team->addMediaFromRequest('logo')
                ->usingFileName('team_logo_' . $team->id . '_' . now()->timestamp . '.' . $request->file('logo')->getClientOriginalExtension())
                ->toMediaCollection('logo');

            $team->update([
                'logo_type'       => 'upload',
                'default_logo_id' => null,
            ]);
        } else {
            // Switching to a default logo — remove any uploaded media
            $team->clearMediaCollection('logo');

            $team->update([
                'logo_type'       => 'default',
                'default_logo_id' => $request->default_logo_id,
            ]);
        }

        return $this->jsonResponse([
            'logo_url' => $team->logo_url,
        ], 'Team logo updated successfully');
    }

    // -------------------------------------------------------------------------

    private function authorizeTeamOwner(Request $request, Team $team): void
    {
        if ($team->user_id !== $request->user()->id) {
            abort(response()->json([
                'status'  => 403,
                'message' => 'You do not own this team.',
                'results' => null,
            ], 403));
        }
    }
}
