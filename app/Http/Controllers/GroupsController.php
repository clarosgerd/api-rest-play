<?php

namespace App\Http\Controllers;

use App\Filters\GroupsFilter;
use App\Http\Requests\StoreGroupsRequest;
use App\Http\Requests\UpdateGroupsRequest;
use App\Http\Resources\GroupsCollection;
use App\Http\Resources\GroupsResource;
use App\Models\GroupMembers;
use App\Models\Groups;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $filter = new GroupsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new GroupsCollection(Groups::paginate());
        } else {
            $group = Groups::where($queryItems)->paginate();
        }

        return new GroupsCollection($group->appends($request->query()));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(StoreGroupsRequest $request) {}

    /**
     * Store a newly created resource in storage.
     */
    /*
    public function store( Request $request ) {
        $groups = new Groups;
        $groups->away_team_id = $request->input( 'name' );
        $groups->description = $request->input( 'description' );
        $groups->creator_id = $request->input( 'creator_id' );
        $groups->is_public = $request->input( 'is_public' );
        $groups->invitation_code = $request->input( 'invitation_code' );
        $groups->max_members = $request->input( 'max_members' );
        $groups->creation_date = $request->input( 'creation_date' );
        $groups->group_balance = $request->input( 'group_balance' );
        $groups->save();
        return response()->json( [
            'message' => 'groups created successfully',
            'task' => $groups
        ] );
    }*/
    public function store(StoreGroupsRequest $request)
    {
        return new GroupsResource(Groups::create($request->all()));
    }

    public function insertFirt(StoreGroupsRequest $request)
    {

        DB::transaction(function () use ($request) {

            $group = new Groups;
            $group->tournament_id = $request->get('tournament_id');
            $group->name = $request->get('name');
            $group->description = $request->get('description');
            $group->creator_id = $request->get('creator_id');
            $group->is_public = $request->get('is_public');
            $group->invitation_code = Str::upper(Str::random(7));
            $group->max_members = $request->get('max_members');
            $group->bet_amount = $request->get('bet_amount', 0);
            $group->prize_1st_pct = $request->get('prize_1st_pct', 60);
            $group->prize_2nd_pct = $request->get('prize_2nd_pct', 30);
            $group->prize_3rd_pct = $request->get('prize_3rd_pct', 10);
            $group->pool_active = $request->get('pool_active', false);
            $group->exact_score_points = $request->get('exact_score_points', 10);
            $group->match_result_points = $request->get('match_result_points', 5);
            $group->correct_goal_points = $request->get('correct_goal_points', 3);
            $group->unique_exact_points = $request->get('unique_exact_points', 15);
            $group->multiplier_dieciseisavos = $request->get('multiplier_dieciseisavos', 1.5);
            $group->multiplier_octavos = $request->get('multiplier_octavos', 2.0);
            $group->multiplier_cuartos = $request->get('multiplier_cuartos', 3.0);
            $group->multiplier_semifinal = $request->get('multiplier_semifinal', 5.0);
            $group->multiplier_final = $request->get('multiplier_final', 10.0);
            $group->created_at = Carbon::now();
            $group->save();
            // $lastInsertedId = $group->group_id;

            $member = new GroupMembers;
            //    $member->group_id = $lastInsertedId;
            $member->groups()->associate($group);
            $member->user_id = $request->get('creator_id');
            $member->joined_at = Carbon::now();
            $member->is_admin = $request->get('is_admin') ?? 1;
            $member->save();

            //     return new GroupsResource($group);
        });

        // dd (Groups::all());
        return new GroupsResource(Groups::all()->last());
    }

    /**
     * Display the specified resource.
     */
    /*

    public function show( String $id ) {
        $data = Groups::find( $id );
        return response()->json( [ 'data' => $data ] );
    }*/

    public function show(Groups $group)
    {
        return new GroupsResource($group);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Groups $groups)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    /*
    public function update( Request $request, String $id ) {
        $groups = Groups::find( $id );

        $groups->away_team_id = $request->input( 'name' );
        $groups->description = $request->input( 'description' );
        $groups->creator_id = $request->input( 'creator_id' );
        $groups->is_public = $request->input( 'is_public' );
        $groups->invitation_code = $request->input( 'invitation_code' );
        $groups->max_members = $request->input( 'max_members' );
        $groups->creation_date = $request->input( 'creation_date' );
        $groups->group_balance = $request->input( 'group_balance' );
        $groups->save();
        return response()->json( [
            'message' => 'Matches created successfully',
            'matches' => $groups

        ] );
    }
*/
    public function update(UpdateGroupsRequest $request, Groups $group)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($group->creator_id !== $userId && ! $isAdmin) {
            $isGroupAdmin = GroupMembers::where('group_id', $group->group_id)
                ->where('user_id', $userId)
                ->where('is_admin', 1)
                ->where('status', 'accepted')
                ->exists();

            if (! $isGroupAdmin) {
                return response()->json(['message' => 'No tienes permiso para modificar este grupo.'], 403);
            }
        }

        $hasMembers = GroupMembers::where('group_id', $group->group_id)
            ->where('status', 'accepted')
            ->count() > 1;

        $scoringFields = [
            'exact_score_points', 'match_result_points', 'correct_goal_points', 'unique_exact_points',
            'multiplier_dieciseisavos', 'multiplier_octavos', 'multiplier_cuartos',
            'multiplier_semifinal', 'multiplier_final',
        ];

        if ($hasMembers) {
            $data = $request->all();
            foreach ($scoringFields as $field) {
                if (array_key_exists($field, $data) && $data[$field] != $group->{$field}) {
                    return response()->json([
                        'message' => 'No se pueden modificar las reglas de scoring mientras hay predictores activos.',
                    ], 422);
                }
            }
        }

        $group->update($request->all());

        return new GroupsResource($group->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $group = Groups::find($id);
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($group->creator_id !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para eliminar este grupo.'], 403);
        }

        $group->delete();

        return response()->json([
            'message' => 'Groups deleted successfully',
        ]);
    }

    public function toggleActive(Groups $group)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($group->creator_id !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para modificar este grupo.'], 403);
        }

        $group->active = $group->active ? 0 : 1;
        $group->save();

        return new GroupsResource($group->fresh());
    }

    public function myMemberships(Request $request, int $tournamentId)
    {
        $user = $request->user();
        $groupIds = Groups::where('tournament_id', $tournamentId)->pluck('group_id');

        $memberships = GroupMembers::whereIn('group_id', $groupIds)
            ->where('user_id', $user->user_id)
            ->select('group_id', 'status', 'is_admin')
            ->get();

        return response()->json(['data' => $memberships]);
    }

    public function myTournamentMemberships(Request $request)
    {
        $user = $request->user();

        $tournamentIds = GroupMembers::where('user_id', $user->user_id)
            ->join('groups', 'groupmembers.group_id', '=', 'groups.group_id')
            ->whereNotNull('groups.tournament_id')
            ->pluck('groups.tournament_id')
            ->unique()
            ->values();

        return response()->json(['data' => $tournamentIds]);
    }
}
