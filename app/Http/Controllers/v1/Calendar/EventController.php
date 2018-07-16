<?php

namespace App\Http\Controllers\v1\Calendar;

use App\Models\User;
use App\Models\Asso;
use App\Models\Event;
use App\Models\Calendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Services\Visible\Visible;
use App\Interfaces\CanHaveCalendars;
use App\Traits\HasVisibility;

/**
 * @resource CalendarEvent
 *
 * Gestion des évenements des calendriers
 */
class EventController extends AbstractController
{
	public function __construct() {
		parent::__construct();

		$this->middleware(
			array_merge(
				\Scopes::matchOneOfDeepestChildren('user-get-calendars', 'client-get-calendars'),
				\Scopes::matchOneOfDeepestChildren('user-get-events', 'client-get-events')
			),
			['only' => ['index', 'show']]
		);
		$this->middleware(
			array_merge(
				\Scopes::matchOneOfDeepestChildren('user-edit-calendars', 'client-edit-calendars'),
				\Scopes::matchOneOfDeepestChildren('user-get-events', 'client-get-events')
			),
			['only' => ['update', 'store', 'destroy']]
		);
	}

	protected function getEventFromCalendar(Request $request, User $user, Calendar $calendar, int $id) {
		$event = $calendar->events()->with(['owned_by', 'created_by', 'visibility', 'details', 'location'])->find($id);

		if ($event) {
			if (!$this->tokenCanSee($request, $event, 'get', 'events'))
				abort(403, 'L\'application n\'a pas les droits sur cet évènenement');

			if ($user && !$this->isVisible($event, $user->id))
				abort(403, 'Vous n\'avez pas les droits sur cet évènenement');

			return $event;
		}

		abort(404, 'L\'évènement n\'existe pas ou ne fait pas parti du calendrier');
	}

	public function isPrivate($user_id, $model = null) {
		if ($model === null)
			return false;

		// Si c'est privée, uniquement les followers et ceux qui possèdent le droit peuvent le voir
		if ($model instanceof Calendar) {
			if ($model->followers()->wherePivot('user_id', $user_id)->exists())
				return true;
		}
		else if ($model instanceof Event) {
			// Si c'est privée, uniquement les personnes ayant un calendrier contenant cet event peuvent le voir
			$user = User::find($user_id);
			$calendar_ids = $user->calendars->pluck('id')->merge($user->followedCalendars->pluck('id'));
			$event_calendar_ids = $model->calendars->pluck('id');

			$model->makeHidden('calendars');

			if (count($calendar_ids->intersect($event_calendar_ids)) !== 0)
				return true;
		}

		return $model->owned_by->isCalendarAccessibleBy($user_id);
    }

	/**
	 * List Calendars
	 *
	 * @return JsonResponse
	 */
	public function index(Request $request, int $calendar_id): JsonResponse {
		$calendar = $this->getCalendar($request, \Auth::user(), $calendar_id);
		$events = $calendar->events()->with(['visibility', 'location', 'created_by', 'owned_by'])->get()->filter(function ($event) use ($request) {
			return $this->tokenCanSee($request, $event, 'get', 'events');
		})->values()->map(function ($event) use ($request) {
			return $this->hideEventData($request, $event);
		});

		return response()->json($events, 200);
	}

	/**
	 * Create Calendar
	 *
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function store(Request $request, int $calendar_id): JsonResponse {
		$calendar = $this->getCalendar($request, $calendar_id, true);
		$user = \Auth::user();

		$events = [];

		if ($request->filled('event_ids')) {
			foreach ($request->input('event_ids') as $event_id) {
				$events[] = $this->getEvent($request, $event_id);
				$calendar->events()->attach(end($events));
			}
		}
		else { // event_id
			$events[] = $this->getEvent($request, $request->input('event_id'));
			$calendar->events()->attach($events[0]);
		}

		foreach ($events as $event)
			$this->hideEventData($request, $event);

		return response()->json($events, 201);
	}

	/**
	 * Show Calendar
	 *
	 * @param  int $id
	 * @return JsonResponse
	 */
	public function show(Request $request, int $calendar_id, int $id): JsonResponse {
		$calendar = $this->getCalendar($request, \Auth::user(), $calendar_id);
		$event = $this->getEventFromCalendar($request, \Auth::user(), $calendar, $id);

		return response()->json($this->hideEventData($request, $event), 200);
	}

	/**
	 * Update Calendar
	 *
	 * @param Request $request
	 * @param  int $id
	 */
	public function update(Request $request, int $calendar_id, int $id): JsonResponse {
		abort(405);
	}

	/**
	 * On retire l'évènement du calendrier courant
	 *
	 * @param  int $id
	 * @return JsonResponse
	 */
	public function destroy(Request $request, int $calendar_id, $id): JsonResponse {
		$calendar = $this->getCalendar($request, \Auth::user(), $calendar_id);
		$event = $this->getEventFromCalendar($request, \Auth::user(), $calendar, $id);

		$calendar_ids = $event->owner->calendars()->get(['calendars.id'])->pluck('id');
		$event_calendar_ids = $event->calendars()->get(['calendars.id'])->pluck('id');

		// On vérifie que celui qui possède l'event, possède l'évènement dans au moins 2 de ses calendriers
		if (count($calendar_ids->intersect($event_calendar_ids)) === 1 && $calendar_ids->contains($calendar_id))
			abort(400, 'L\'évènement doit au moins appartenir à un calendrier du propriétaire de l\'évènement');

		$calendar->events()->detach($event);
		abort(204);
	}
}
