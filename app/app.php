<?php
    date_default_timezone_set('America/Los_Angeles');
    require_once __DIR__."/../vendor/autoload.php";
    require_once __DIR__."/../src/Activity.php";
    require_once __DIR__."/../src/City.php";
    require_once __DIR__."/../src/Review.php";
    require_once __DIR__."/../src/Trip.php";
    require_once __DIR__."/../src/User.php";

    use Symfony\Component\Debug\Debug;
    Debug::enable();

    session_start();
    if (empty($_SESSION['current_user'])) {
        $_SESSION['current_user'] = null;
    }

    $app = new Silex\Application();

    $app['debug'] = true;

    $server = 'mysql:host=localhost;dbname=trip_planner';
    $username = 'root';
    $password = 'root';
    $DB = new PDO($server, $username, $password);

    $app->register(new Silex\Provider\TwigServiceProvider(), array('twig.path' => __DIR__.'/../views'));

    use Symfony\Component\HttpFoundation\Request;
    Request::enableHttpMethodParameterOverride();

// home page
    $app->get('/', function() use ($app) {
        return $app['twig']->render('index.html.twig', array('alert' => null, 'current_user' => $_SESSION['current_user']));
    });

// show cities of a state
    $app->get('/search_results/{state}', function($state) use ($app) {
        $states = City::getStates();
        $cities = City::citiesInState($state);
        return $app['twig']->render('search_results.html.twig', array('states' => $states, 'results' => $cities, 'current_user' => $_SESSION['current_user']));
    });

// to search results
    $app->get('/search_results', function() use ($app) {
        $states = City::getStates();
        return $app['twig']->render('search_results.html.twig', array('states' => $states, 'results' => null, 'current_user' => $_SESSION['current_user']));
    });

// search results
    $app->post('/search_results', function() use ($app) {
        $search_results = City::search($_POST['search_input']);
        $states = City::getStates();
        return $app['twig']->render('search_results.html.twig', array('states' => $states, 'results' => $search_results, 'current_user' => $_SESSION['current_user']));
    });

// appends all cities to right column when state is clicked
    $app->get('/citiesByState/{state}', function($state) use ($app) {
        $cities = City::citiesInState($state);
        return $app['twig']->render('browse.html.twig', array('states' => $states, 'cities' => $cities, 'current_user' => $_SESSION['current_user']));
    });

// leads to individual city page
    $app->get('/city/{id}', function($id) use ($app) {
        $city = City::findById($id);
        return $app['twig']->render('city.html.twig', array('city' => $city, 'current_user' => $_SESSION['current_user']));
    });

// signup page
    $app->get('/sign_up', function() use ($app) {
        return $app['twig']->render('sign_up.html.twig', array('alert' => null, 'current_user' => $_SESSION['current_user']));
    });

// submit sign up form
    $app->post('/sign_up', function() use ($app) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $new_user = new User($username, $password);
        $valid = $new_user->save();
        if ($valid == true) {
            $_SESSION['current_user'] = $new_user;
            return $app['twig']->render('user_dashboard.html.twig', array('user' => $new_user, 'alert' => 'login-success', 'current_user' => $_SESSION['current_user']));
        } else {
            return $app['twig']->render('sign_up.html.twig', array('alert' => 'signup', 'current_user' => $_SESSION['current_user']));
        }
    });

// login page
    $app->get('/log_in', function() use ($app) {
        return $app['twig']->render('log_in.html.twig', array('alert' => null, 'current_user' => $_SESSION['current_user']));
    });

// submit login form
    $app->post('/login', function() use ($app) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $valid = User::verifyLogin($username, $password);
        if ($valid == false) {
            return $app['twig']->render('log_in.html.twig', array('alert' => 'login', 'current_user' => $_SESSION['current_user']));
        }
        return $app['twig']->render('user_dashboard.html.twig', array('current_user' => $_SESSION['current_user'], 'alert' => 'login-success'));
    });

// log out
    $app->get('/logout', function() use ($app) {
        $_SESSION['current_user'] = null;
        return $app['twig']->render('index.html.twig', array('alert' => 'logout', 'current_user' => $_SESSION['current_user']));
    });


// ---------------------- USER DASHBOARD INTERFACE -------------------------

// to dashboard
    $app->get('/dashboard/{id}', function($id) use ($app) {
        $user = User::findById($id);
        return $app['twig']->render('user_dashboard.html.twig', array('user' => $user, 'current_user' => $_SESSION['current_user']));
    });

// list of past trips
    $app->get('/past_trips/{id}', function($id) use ($app) {
        $user = User::findById($id);
        $trips = $user->getPastTrips();
        return $app['twig']->render('past_trips.html.twig', array('user' => $user, 'trips' => $trips, 'current_user' => $_SESSION['current_user']));
    });

// pending user trips
    $app->get('/pending_trips/{id}', function($id) use ($app) {
        $user = User::findById($id);
        $trips = $user->getPendingTrips();
        return $app['twig']->render('pending_trips.html.twig', array('user' => $user, 'trips' => $trips, 'current_user' => $_SESSION['current_user']));
    });

// view trip page
    $app->get('/trip/{id}', function($id) use ($app) {
        $trip = Trip::findById($id);
        $review = $trip->getReview();
        $user = User::findById($trip->getUserId());
        $activities = $trip->getActivities();
        $cities = $trip->getCities();
        return $app['twig']->render('trip.html.twig', array('trip' => $trip, 'review' => $review, 'user' => $user, 'activities' => $activities, 'trip_cities' => $cities, 'alert' => null, 'current_user' => $_SESSION['current_user'], 'all_cities' => City::getAll()));
    });

// delete trip
    $app->delete('/trip/delete/{id}', function($id) use ($app) {
        $found_trip = Trip::findById($id);
        $user_id = $found_trip->getUserId();
        $found_trip->delete();

        return $app->redirect('/pending_trips/' . $user_id);
    });


// add activity to trip
    $app->post('/trip/{id}', function($id) use ($app) {
        $trip = Trip::findById($id);
        $name = $_POST['name'];
        $date = $_POST['date'];
        $description = $_POST['description'];
        $trip_id = $trip->getId();
        $new_activity = new Activity($name, $date, $trip_id, $description);
        $new_activity->save();
        return $app->redirect('/trip/' . $id);
    });

// update activity for trip
    $app->patch('/trip/{id}/{activity_id}', function($id, $activity_id) use ($app) {
        $name = $_POST['name'];
        $date = $_POST['date'];
        $description = $_POST['description'];
        $trip_id = $id;
        $new_activity = Activity::findById($activity_id);
        $new_activity->update($name, $date, $description);
        return $app->redirect('/trip/' . $id);
    });

// delete activity for trip
    $app->delete('/trip/{id}', function($id) use ($app) {
        $found_activity = Activity::findById($_POST['activity_id']);
        $found_activity->delete();
        return $app->redirect('/trip/' . $id);
    });

// add city to trip
    $app->post('/trip/city/{id}', function($id) use ($app) {
        $trip = Trip::findById($id);
        $trip->addCity($_POST['city_id']);
        return $app->redirect('/trip/' . $id);
    });

// create and add city to trip
    $app->post('/trip/new_city/{id}', function($id) use ($app) {
        $name = $_POST['name'];
        $state = $_POST['state'];
        $new_city = new City($name, $state);
        $new_city->save();
        $trip = Trip::findById($id);
        $trip->addCity($new_city->getId());
        return $app->redirect('/trip/' . $id);
    });

// remove city from trip
    $app->delete('/trip/remove_city/{id}', function($id) use ($app) {
        $trip = Trip::findById($id);
        $trip->removeCity($_POST['city_id']);
        return $app->redirect('/trip/' . $id);
    });

// to city page
    $app->get('/city/{id}', function($id) use ($app) {
        $city = City::findById($id);
        return $app['twig']->render('city.html.twig', array('city' => $city));
    });


// new trip page
    $app->get('/new_trip/{id}', function($id) use ($app) {
        $user = User::findById($id);
        return $app['twig']->render('new_trip.html.twig', array('user' => $user, 'current_user' => $_SESSION['current_user']));
    });

// add new trip
    $app->post('/new_trip/{id}', function($id) use ($app) {
        $user = User::findById($id);
        $name = $_POST['purpose'];
        $user_id = $user->getId();
        $description = $_POST['description'];
        $new_trip = new Trip($name, $user_id, 0, $description);
        $new_trip->save();
        return $app['twig']->render('trip.html.twig', array('trip' => $new_trip, 'review' => null, 'user' => $user, 'activities' => null, 'trip_cities' => null, 'alert' => null, 'current_user' => $_SESSION['current_user'], 'all_cities' => City::getAll()));
    });

// review trip
    $app->post('/review_form/{id}', function($id) use ($app) {
        $trip_id = $id;
        $title = $_POST['title'];
        $description = $_POST['description'];
        $rating = $_POST['rating'];
        $new_review = new Review($title, $description, $rating, $trip_id);
        $new_review->save();
        $trip = Trip::findById($id);
        $trip->completeTrip();
        return $app->redirect('/trip/' . $id);
    });

// to city page
    $app->get('/city/{id}', function($id) use ($app) {
        $city = City::findById($id);
        return $app['twig']->render('city.html.twig', array('city' => $city, 'current_user' => $_SESSION['current_user']));
    });

// route to individual past trip page ( NOT DONT YET )
    $app->get('/past_trip/{id}', function($id) use ($app) {
        $trip = Trip::findById($id);
        $review = $trip->getReview();
        $user = User::findById($trip->getUserId());
        $activities = $trip->getActivities();
        $cities = $trip->getCities();

        return $app['twig']->render('past_trip.html.twig', array('trip' => $trip, 'review' => $review, 'user' => $user, 'activities' => $activities, 'trip_cities' => $cities, 'alert' => null, 'current_user' => $_SESSION['current_user'], 'all_cities' => City::getAll()));
    });

    $app->post('/past_trip/{id}', function($id) use ($app) {
        $trip = Trip::findById($id);
        $name = $_POST['name'];
        $date = $_POST['date'];
        $description = $_POST['description'];
        $trip_id = $trip->getId();
        $new_activity = new Activity($name, $date, $trip_id, $description);
        $new_activity->save();
        return $app->redirect('/past_trip/' . $id);
    });

    $app->patch('/past_trip/{id}/{activity_id}', function($id, $activity_id) use ($app) {
        $name = $_POST['name'];
        $date = $_POST['date'];
        $description = $_POST['description'];
        $trip_id = $id;
        $new_activity = Activity::findById($activity_id);
        $new_activity->update($name, $date, $description);
        return $app->redirect('/past_trip/' . $id);
    });

    $app->delete('/past_trip/delete/{id}', function($id) use ($app) {
        $found_trip = Trip::findById($id);
        $user_id = $found_trip->getUserId();
        $found_trip->delete();

        return $app->redirect('/past_trips/' . $user_id);
    });

    return $app;

?>
