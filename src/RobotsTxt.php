<?php

namespace nickmoline\RobotsChecker;

use League\Uri\Schemes\Http as HttpUri;
use tomverran\Robot\RobotsTxt as TVRobots;

class RobotsTxt extends Robots
{
    private $robots;
    private $robotsContents;
    private $hasRobotsFile = false;
    private $isAllowed = true;

    public static function createFromExisting(Robots $existing, Robots $robots = null)
    {
        if (!$robots) {
            $robots = new RobotsTxt();
        }

        $robots = Robots::createFromExisting($existing, $robots);

        return $robots;
    }

    public function setURL($url)
    {
        parent::setURL($url);
        $this->robots = null;
        $this->robotsContents = null;

        return $this;
    }

    public function robotsHandler()
    {
        if ($this->robots) {
            return $this->robots;
        }
        $curl = $this->checkerCurl();
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $curl->setOpt(CURLOPT_MAXREDIRS, 10);
        $robotsFetch = $curl->get($this->getRobotsUrl());

        if (in_array($curl->http_status_code, [404, 410])) {
            return $this->setRobotsEmpty();
        }

        $this->hasRobotsFile = true;
        $this->setRobotsHandler($curl->response);

        return $this->robots;
    }

    public function validate($userAgent = "Googlebot")
    {
        $robots = $this->robotsHandler();
        if (!$this->hasRobotsFile) {
            $this->setAllowed(true, "No robots.txt file present", "Allowed");
            return true;
        }

        if ($robots->isAllowed($userAgent, $this->getRelativeUrl())) {
            $this->setAllowed(true, "Allowed by robots.txt", "Allowed");
            return true;
        }

        $this->setDenied("Denied by robots.txt", "Denied");
        return false;
    }

    public function setRobotsEmpty()
    {
        $this->hasRobotsFile = false;
        return $this->setRobotsHandler("User-agent: *\nAllow: /");
    }

    public function setRobotsHandler($contents = "User-agent: *\nAllow: /")
    {
        $this->robotsContents = $contents;
        $this->robots = new TVRobots($this->robotsContents);

        return $this->robots;
    }

    public function getRobotsUrl()
    {
        return HttpUri::createFromString($this->url)->withPath("/robots.txt");
    }
}