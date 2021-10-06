<?php

namespace App\Models;

use FourelloDevs\GranularSearch\Abstracts\AbstractGranularUserModel;
use FourelloDevs\MagicController\Casts\BCryptable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;

/**
 * Class User
 * @package App\Models
 *
 * @method static Builder ofUsername(string $username)
 */
class User extends AbstractGranularUserModel
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => BCryptable::class,
    ];

    /***** GRANULAR SEARCH SETUP *****/

    protected static $granular_excluded_keys = [
        'email_verified_at',
        'deleted_at',
        'created_at',
        'updated_at',
        'remember_token'
    ];

    protected static $granular_like_keys = [
        'first_name',
        'middle_name',
        'last_name'
    ];

    protected static $granular_allowed_relations = [
        // function names of the related models
    ];

    protected static $granular_q_relations = [
        // function names of the related models
    ];

    /***** SCOPES *****/

    public function scopeOfUsername(Builder $query, string $username){
        return $query->search(['email' => $username, 'username' => $username], false, true, true);
    }

    /***** LARAVEL PASSPORT AUTHENTICATION RELATED *****/

    /**
     * Overriding Passport: Find the user instance for the given username.
     *
     * @param string $username
     * @return Builder
     */
    public function findForPassport(string $username): Builder
    {
        return static::ofUsername($username);
    }

    /**
     * Overriding Passport: Validate username and password.
     *
     * @param $username
     * @param $password
     * @return Builder|Model|object|null
     */

    public function findAndValidateForPassport($username, $password)
    {
        $user = static::ofUsername($username)->first();

        if(is_null($user) === false && Hash::check($password, $user->password)){
            return $user;
        }

        return null;
    }
}
