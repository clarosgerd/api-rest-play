<?php

namespace App\Http\Controllers;

use App\Filters\GroupMembersFilter;
use App\Http\Requests\StoreGroupMembersRequest;
use App\Http\Requests\UpdateGroupMembersRequest;
use App\Http\Resources\GroupMembersCollection;
use App\Http\Resources\GroupMembersResource;
use App\Models\GroupMembers;
use App\Models\Groups;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GroupMembersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $filter = new GroupMembersFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new GroupMembersCollection(GroupMembers::paginate());
        } else {
            $groupmembers = GroupMembers::where($queryItems)->paginate();
        }

        return new GroupMembersCollection($groupmembers->appends($request->query()));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupMembersRequest $request)
    {
        return new GroupMembersResource(GroupMembers::create($request->all()));
    }

    /**
     * Display the specified resource.
     */
    public function show(GroupMembers $member)
    {
        return new GroupMembersResource($member);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GroupMembers $groupMembers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupMembersRequest $request, GroupMembers $member)
    {
        $member->update($request->all());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $groupmembers = GroupMembers::find($id);
        $groupmembers->delete();

        return response()->json([
            'message' => 'GroupMembers deleted successfully',
        ]);
    }

    /**
     * Request to join a group (creates pending member).
     */
    public function joinRequest(Request $request, Groups $group)
    {
        $user = $request->user();

        $hasRules = ($group->exact_score_points > 0)
            || ($group->match_result_points > 0)
            || ($group->correct_goal_points > 0);

        if (! $hasRules) {
            return response()->json(['message' => 'El grupo debe tener reglas de scoring configuradas antes de aceptar predictores'], 422);
        }

        // Check if already a member (any status)
        $existing = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $user->user_id)
            ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json(['message' => 'Ya tienes una solicitud pendiente en este grupo'], 422);
            }

            return response()->json(['message' => 'Ya eres miembro de este grupo'], 422);
        }

        // Check max members
        $currentCount = GroupMembers::where('group_id', $group->group_id)
            ->where('status', 'accepted')
            ->count();

        if ($currentCount >= $group->max_members) {
            return response()->json(['message' => 'El grupo está lleno'], 422);
        }

        $member = new GroupMembers;
        $member->group_id = $group->group_id;
        $member->user_id = $user->user_id;
        $member->joined_at = Carbon::now();
        $member->is_admin = 0;
        $member->status = 'pending';
        $member->save();

        return new GroupMembersResource($member);
    }

    /**
     * Approve a pending join request (group admin only).
     */
    public function approve(Request $request, GroupMembers $member)
    {
        $user = $request->user();

        // Check requester is group admin
        $isAdmin = GroupMembers::where('group_id', $member->group_id)
            ->where('user_id', $user->user_id)
            ->where('is_admin', 1)
            ->where('status', 'accepted')
            ->exists();

        if (! $isAdmin) {
            return response()->json(['message' => 'No tienes permisos para aprobar miembros'], 403);
        }

        $group = Groups::find($member->group_id);
        $hasRules = ($group->exact_score_points > 0)
            || ($group->match_result_points > 0)
            || ($group->correct_goal_points > 0);

        if (! $hasRules) {
            return response()->json(['message' => 'El grupo debe tener reglas de scoring configuradas antes de aceptar predictores'], 422);
        }

        if ($member->status !== 'pending') {
            return response()->json(['message' => 'Esta solicitud no está pendiente'], 422);
        }

        $member->status = 'accepted';
        $member->save();

        return new GroupMembersResource($member);
    }

    /**
     * Reject a pending join request (group admin only).
     */
    public function reject(Request $request, GroupMembers $member)
    {
        $user = $request->user();

        $isAdmin = GroupMembers::where('group_id', $member->group_id)
            ->where('user_id', $user->user_id)
            ->where('is_admin', 1)
            ->where('status', 'accepted')
            ->exists();

        if (! $isAdmin) {
            return response()->json(['message' => 'No tienes permisos para rechazar miembros'], 403);
        }

        if ($member->status !== 'pending') {
            return response()->json(['message' => 'Esta solicitud no está pendiente'], 422);
        }

        $member->delete();

        return response()->json(['message' => 'Solicitud rechazada']);
    }

    /**
     * List pending join requests for a group (group admin only).
     */
    public function pending(Request $request, Groups $group)
    {
        $user = $request->user();

        $isAdmin = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $user->user_id)
            ->where('is_admin', 1)
            ->where('status', 'accepted')
            ->exists();

        if (! $isAdmin) {
            return response()->json(['message' => 'No tienes permisos para ver solicitudes'], 403);
        }

        $pending = GroupMembers::where('group_id', $group->group_id)
            ->where('status', 'pending')
            ->with('user')
            ->get();

        return GroupMembersResource::collection($pending);
    }
}
