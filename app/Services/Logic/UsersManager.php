<?php

namespace STS\Services\Logic; 

use \STS\Exceptions\ValidationException;
use STS\Repository\UserRepository;
use STS\Entities\Trip;
use STS\User;
use Validator;

class UsersManager extends BaseManager
{

    protected $repo;
    public function __construct()
    { 
        $this->repo = new UserRepository();
    } 

    public function validator(array $data, $id = null)
    {
        if ($id) {
            return Validator::make($data, [
                'name' => 'max:255',
                'email' => 'email|max:255|unique:users,email' . $id,
                'password' => 'min:6|confirmed',            
            ]);
        } else {
            return Validator::make($data, [
                'name' => 'required|max:255',
                'email' => 'required|email|max:255|unique:users',
                'password' => 'required|min:6|confirmed',            
            ]);
        }
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    public function create(array $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else { 
            $data['password'] = bcrypt($data['password']);
            $u = $this->repo->create($data);
            return $u;
        } 
    }

    public function update($user, array $data)
    {
        $v = $this->validator($data, $user->id);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else { 
            if (isset($data['password'])) {
                $data["password"] = bcrypt($data['password']);
            }
            
            $u = $this->repo->update($user, $data);
            return $u;
        } 
    }


    public function acceptTerms($user) 
    {
        $user->terms_and_conditions = true;
        $user->save();
        return $user;
    }

    public function show($user, $profile_id)
    { 
        $profile = $this->repo->show($profile_id);
        if ($profile) {
            $profile->cantidadViajes = $this->tripsCount($profile);
            $profile->distanciaRecorrida = $this->tripsDistance($profile);
            if ($user->id != $profile->id) {
                $user_id = $user->id;
                $patente = $profile->trips()->whereHas('passenger', function ($q) use ($user_id) {
                    $q->whereUserId($user_id);
                    $q->whereRequestState(Passenger::STATE_ACEPTADO);
                })->first();
                if (is_null($patente)) {
                    $profile->patente = null;
                    $user_id = $profile->id;
                    $dni = $user->trips()->whereHas('passenger', function ($q) use ($user_id) {
                        $q->whereUserId($user_id);
                        $q->whereRequestState(Passenger::STATE_ACEPTADO);
                    })->first();
                    if (is_null($dni)) {
                        $profile->nro_doc = null;
                    }
                }
            }
            return $profile;
        }
        return null;
    }

    public function tripsCount($user, $type = null)
	{
		$cantidad = 0;
		if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
			$cantidad += $user->trips(Trip::FINALIZADO)->count();
		}
		if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
			$cantidad += $user->tripsAsPassenger(Trip::FINALIZADO)->count();
		}
		return $cantidad;
	}

	public function tripsDistance($user, $type = null)
	{
		$distancia = 0;
		if ($type == Passenger::TYPE_CONDUCTOR || is_null($type)) {
			$distancia += $user->trips(Trip::FINALIZADO)->sum("distance");
		}
		if ($type == Passenger::TYPE_PASAJERO || is_null($type)) {
			$distancia += $user->tripsAsPassenger(Trip::FINALIZADO)->sum("distance");
		}
		return $distancia;
	}

}