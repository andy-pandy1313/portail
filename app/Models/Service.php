<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
	use SoftDeletes;

	protected $casts = [
		'deleted_at' => 'datetime',
	];

	protected $fillable = [
		'name', 'shortname', 'login', 'image', 'description', 'url', 'visibility_id'
	];

	protected $with = [
		'visibility',
	];

	protected $must = [
		'name', 'shortname', 'login', 'image', 'description', 'url'
	];

	protected $selection = [
		'order' => 'oldest',
		'filter' => [],
	];

	public function visibility() {
		return $this->belongsTo(Visibility::class);
	}

	public function followers() {
		return $this->hasMany(User::class, 'services_followers');
	}
}
