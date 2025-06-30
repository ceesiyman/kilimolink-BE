<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ConsultationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/experts",
     *     summary="Get all experts",
     *     tags={"Consultations"},
     *     @OA\Response(
     *         response=200,
     *         description="List of experts",
     *         @OA\JsonContent(
     *             @OA\Property(property="experts", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getExperts()
    {
        $experts = User::where('role', 'expert')->get();
        return response()->json(['experts' => $experts]);
    }

    /**
     * @OA\Post(
     *     path="/api/consultations",
     *     summary="Book a consultation",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"expert_id", "consultation_date", "description"},
     *             @OA\Property(property="expert_id", type="integer"),
     *             @OA\Property(property="consultation_date", type="string", format="date-time"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Consultation booked successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expert_id' => 'required|exists:users,id,role,expert',
            'consultation_date' => 'required|date|after:now',
            'description' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $consultation = Consultation::create([
            'farmer_id' => Auth::id(),
            'expert_id' => $request->expert_id,
            'consultation_date' => $request->consultation_date,
            'description' => $request->description,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Consultation booked successfully',
            'consultation' => $consultation->load(['expert', 'farmer'])
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/consultations/my-bookings",
     *     summary="Get farmer's consultations",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of farmer's consultations"
     *     )
     * )
     */
    public function myBookings()
    {
        $consultations = Consultation::where('farmer_id', Auth::id())
            ->with(['expert'])
            ->latest()
            ->get();

        return response()->json(['consultations' => $consultations]);
    }

    /**
     * @OA\Get(
     *     path="/api/consultations/my-expert-bookings",
     *     summary="Get expert's consultations",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of expert's consultations"
     *     )
     * )
     */
    public function myExpertBookings()
    {
        $consultations = Consultation::where('expert_id', Auth::id())
            ->with(['farmer'])
            ->latest()
            ->get();

        return response()->json(['consultations' => $consultations]);
    }

    /**
     * @OA\Patch(
     *     path="/api/consultations/{id}/accept",
     *     summary="Accept a consultation request",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="expert_notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Consultation accepted successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Consultation not found")
     * )
     */
    public function accept(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        if ($consultation->expert_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($consultation->status !== 'pending') {
            return response()->json(['message' => 'Consultation is not in pending status'], 422);
        }

        $consultation->update([
            'status' => 'accepted',
            'expert_notes' => $request->expert_notes
        ]);

        return response()->json([
            'message' => 'Consultation accepted successfully',
            'consultation' => $consultation->load(['farmer', 'expert'])
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/consultations/{id}/decline",
     *     summary="Decline a consultation request",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"decline_reason"},
     *             @OA\Property(property="decline_reason", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Consultation declined successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Consultation not found")
     * )
     */
    public function decline(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'decline_reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $consultation = Consultation::findOrFail($id);

        if ($consultation->expert_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($consultation->status !== 'pending') {
            return response()->json(['message' => 'Consultation is not in pending status'], 422);
        }

        $consultation->update([
            'status' => 'declined',
            'decline_reason' => $request->decline_reason
        ]);

        return response()->json([
            'message' => 'Consultation declined successfully',
            'consultation' => $consultation->load(['farmer', 'expert'])
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/consultations/{id}/complete",
     *     summary="Mark a consultation as completed",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Consultation marked as completed"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Consultation not found")
     * )
     */
    public function complete($id)
    {
        $consultation = Consultation::findOrFail($id);

        if ($consultation->expert_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($consultation->status !== 'accepted') {
            return response()->json(['message' => 'Consultation must be accepted before completing'], 422);
        }

        $consultation->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Consultation marked as completed',
            'consultation' => $consultation->load(['farmer', 'expert'])
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/consultations/{id}/cancel",
     *     summary="Cancel a consultation",
     *     tags={"Consultations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Consultation cancelled successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Consultation not found")
     * )
     */
    public function cancel($id)
    {
        $consultation = Consultation::findOrFail($id);

        if (!in_array(Auth::id(), [$consultation->farmer_id, $consultation->expert_id])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($consultation->status, ['pending', 'accepted'])) {
            return response()->json(['message' => 'Consultation cannot be cancelled in its current status'], 422);
        }

        $consultation->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Consultation cancelled successfully',
            'consultation' => $consultation->load(['farmer', 'expert'])
        ]);
    }
}
