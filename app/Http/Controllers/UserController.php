<?php

namespace App\Http\Controllers;

use App\Events\User\UserRestored;
use App\Http\Requests\User\RefreshTokenRequest;
use App\Http\Requests\User\RestoreUser;
use App\Http\Resources\UserResource;
use App\Models\User;

// Custom Requests
use App\Http\Requests\User\IndexUser;
use App\Http\Requests\User\ShowUser;
use App\Http\Requests\User\CreateUser;
use App\Http\Requests\User\UpdateUser;
use App\Http\Requests\User\DeleteUser;

// Custom Events
use App\Events\User\UserCollected;
use App\Events\User\UserFetched;
use App\Events\User\UserCreated;
use App\Events\User\UserUpdated;
use App\Events\User\UserDeleted;
use App\Http\Requests\User\LoginRequest;
use App\Http\Requests\User\LogoutRequest;
use FourelloDevs\MagicController\Exceptions\UnauthorizedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use JsonException;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;

class UserController extends Controller
{
    public function index(IndexUser $request): JsonResponse
    {
        $data = User::search($request);

        if ($request->has('full_data') === TRUE) {
            $data = $data->get();
        }
        else{
            $data = $data->paginate($request->get('per_page', 15));
        }

        event(new UserCollected($data));

        $data = UserResource::collection($data);

        return customResponse()
            ->data($data)
            ->message('Successfully collected record.')
            ->success()
            ->generate();
    }

    public function store(CreateUser $request): JsonResponse
    {
        $data = $request->all();

        $model = User::create($data)->fresh();

        event(new UserCreated($model));

        return customResponse()
            ->data(new UserResource($model))
            ->message('Successfully created record.')
            ->success()
            ->generate();
    }

    public function getSelf(Request $request): JsonResponse
    {
        $user = auth()->user();

        event(new UserFetched($user));

        return customResponse()
            ->data(new UserResource($user))
            ->message('Successfully fetched record.')
            ->success()
            ->generate();
    }

    public function show(ShowUser $request, User $user): JsonResponse
    {
        event(new UserFetched($user));

        return customResponse()
            ->data(new UserResource($user))
            ->message('Successfully fetched record.')
            ->success()
            ->generate();
    }

    public function update(UpdateUser $request, User $user): JsonResponse
    {

        event(new UserUpdated($user));

        return customResponse()
            ->data(new UserResource($user))
            ->message('Successfully updated record.')
            ->success()
            ->generate();
    }

    public function destroy(DeleteUser $request, User $user): JsonResponse
    {
        $user->delete();

        event(new UserDeleted($user));

        return customResponse()
            ->data(new UserResource($user))
            ->message('Successfully deleted record.')
            ->success()
            ->generate();
    }

    public function restore(RestoreUser $request, $user): JsonResponse
    {
        $user = User::withTrashed()->ofUsername($user)->firstOrFail();

        $user->restore();

        event(new UserRestored($user));

        return customResponse()
            ->data(new UserResource($user))
            ->message('Successfully restored record.')
            ->success()
            ->generate();
    }

    /***************AUTHENTICATION RELATED***************/

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     * @throws JsonException|UnauthorizedException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->only('username', 'password');
        $user = User::ofUsername($data['username'])->first();

        if(is_null($user)){
            throw new UnauthorizedException('Incorrect username or password');
        }

        $credentials = $this->buildCredentials($data);

        $response = $this->makeRequest($credentials);

        $data = [
            'user'  => $user,
            'token' => $response
        ];

        return customResponse()
            ->data($data)
            ->slug('login_success')
            ->message('Successfully logged in')
            ->success()
            ->generate();
    }

    /**
     * @param RefreshTokenRequest $request
     * @return JsonResponse
     * @throws JsonException
     * @throws UnauthorizedException
     */
    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        $data = $request->only('refresh_token');

        $credentials = $this->buildCredentials($data, null,'refresh_token');

        $response = $this->makeRequest($credentials);

        return customResponse()
            ->data($response)
            ->message('Tokens refreshed')
            ->success()
            ->generate();
    }

    /**
     *
     * @param LogoutRequest $request
     * @return JsonResponse
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $user = $request->user();

        $tokenId = $request->user()->token()->id;

        $tokenRepository = app(TokenRepository::class);

        $refreshTokenRepository = app(RefreshTokenRepository::class);

        // Revoke an access token...
        $tokenRepository->revokeAccessToken($tokenId);

        // Revoke all of the token's refresh tokens...
        $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($tokenId);

        return customResponse()
            ->data($user)
            ->slug('logout_success')
            ->message('Successfully logged out')
            ->success()
            ->generate();
    }

    /**
     * @param array $args
     * @param string|null $scopes
     * @param string $grant_type
     * @return array
     */
    private function buildCredentials(array $args, ?string $scopes = '', string $grant_type = 'password'): array
    {
        $credentials = collect($args);

        $credentials['client_id'] = env('PGC_ID');
        $credentials['client_secret'] = env('PGC_SECRET');
        $credentials['grant_type'] = $grant_type;
        $credentials['scope'] = $scopes;

        return $credentials->toArray();
    }

    /**
     * @param array $credentials
     * @return mixed
     * @throws \JsonException
     * @throws UnauthorizedException
     */
    private function makeRequest(array $credentials): mixed
    {
        $request = Request::create('oauth/token', 'POST', $credentials, [], [], [
            'HTTP_Accept' => 'application/json',
        ]);

        $response = app()->handle($request);
        $decodedResponse = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() !== 200) {
            if ($decodedResponse['message'] === 'The provided authorization grant (e.g., authorization code, resource owner credentials) or refresh token is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client.') {
                throw new UnauthorizedException('Incorrect username or password');
            }
            throw new UnauthorizedException($decodedResponse['message']);
        }
        return $decodedResponse;
    }
}
