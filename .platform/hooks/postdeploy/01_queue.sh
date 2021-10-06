#!/bin/bash
cp /opt/elasticbeanstalk/deployment/env /opt/elasticbeanstalk/deployment/laravel_env
sudo chmod 644 /opt/elasticbeanstalk/deployment/laravel_env
rm -f /opt/elasticbeanstalk/deployment/*.bak
sudo systemctl enable laravel_queue_worker@{1..2}.service
sudo systemctl restart laravel_queue_worker@{1..2}.service
