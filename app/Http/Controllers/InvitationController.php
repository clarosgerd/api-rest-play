<?php

namespace App\Http\Controllers;

use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $invitations = Invitation::where('email', $user->email)
            ->where('status', 'pending')
            ->with('group')
            ->get();

        return response()->json(['invitations' => $invitations]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:groups,group_id',
            'email' => 'required|email',
        ]);

        $group = Groups::findOrFail($validated['group_id']);

        if ($group->creator_id !== $request->user()->user_id) {
            return response()->json(['message' => 'Only the group creator can send invitations'], 403);
        }

        $existingMember = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (! $existingMember || ! $existingMember->is_admin) {
            return response()->json(['message' => 'Only group admins can send invitations'], 403);
        }

        $existingInvitation = Invitation::where('group_id', $group->group_id)
            ->where('email', $validated['email'])
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json(['message' => 'Invitation already sent to this email'], 409);
        }

        $invitation = Invitation::create([
            'group_id' => $group->group_id,
            'invited_by' => $request->user()->user_id,
            'email' => $validated['email'],
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
            'created_at' => Carbon::now(),
        ]);

        $invitee = User::where('email', $validated['email'])->first();
        if ($invitee) {
            try {
                $invitee->notify(new InvitationNotification(
                    $group,
                    $group->invitation_code,
                    $request->user()
                ));
            } catch (\Exception $e) {
                // Notification failed, continue
            }
        }

        return response()->json([
            'message' => 'Invitation sent successfully',
            'invitation' => $invitation,
            'invitation_code' => $group->invitation_code,
        ], 201);
    }

    public function acceptByCode(Request $request)
    {
        $validated = $request->validate([
            'invitation_code' => 'required|string',
        ]);

        $group = Groups::where('invitation_code', $validated['invitation_code'])->first();

        if (! $group) {
            return response()->json(['message' => 'Invalid invitation code'], 404);
        }

        $userId = $request->user()->user_id;

        $existingMember = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $userId)
            ->first();

        if ($existingMember) {
            return response()->json(['message' => 'You are already a member of this group'], 409);
        }

        if ($group->max_members) {
            $memberCount = GroupMembers::where('group_id', $group->group_id)->count();
            if ($memberCount >= $group->max_members) {
                return response()->json(['message' => 'Group has reached maximum members'], 409);
            }
        }

        GroupMembers::create([
            'group_id' => $group->group_id,
            'user_id' => $userId,
            'joined_at' => Carbon::now(),
            'is_admin' => false,
        ]);

        Invitation::where('group_id', $group->group_id)
            ->where('email', $request->user()->email)
            ->where('status', 'pending')
            ->update(['status' => 'accepted']);

        return response()->json([
            'message' => 'Successfully joined the group',
            'group' => $group,
        ]);
    }

    public function acceptInvitation(Request $request, Invitation $invitation)
    {
        if ($invitation->email !== $request->user()->email) {
            return response()->json(['message' => 'This invitation is not for you'], 403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation is no longer pending'], 409);
        }

        if ($invitation->expires_at && Carbon::parse($invitation->expires_at)->isPast()) {
            $invitation->update(['status' => 'declined']);

            return response()->json(['message' => 'This invitation has expired'], 410);
        }

        $group = $invitation->group;

        $existingMember = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if ($existingMember) {
            return response()->json(['message' => 'You are already a member of this group'], 409);
        }

        if ($group->max_members) {
            $memberCount = GroupMembers::where('group_id', $group->group_id)->count();
            if ($memberCount >= $group->max_members) {
                return response()->json(['message' => 'Group has reached maximum members'], 409);
            }
        }

        GroupMembers::create([
            'group_id' => $group->group_id,
            'user_id' => $request->user()->user_id,
            'joined_at' => Carbon::now(),
            'is_admin' => false,
        ]);

        $invitation->update(['status' => 'accepted']);

        return response()->json([
            'message' => 'Successfully joined the group',
            'group' => $group,
        ]);
    }

    public function destroy(Request $request, Invitation $invitation)
    {
        if ($invitation->invited_by !== $request->user()->user_id) {
            return response()->json(['message' => 'You can only cancel your own invitations'], 403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'Can only cancel pending invitations'], 409);
        }

        $invitation->update(['status' => 'declined']);

        return response()->json(['message' => 'Invitation cancelled']);
    }
}
