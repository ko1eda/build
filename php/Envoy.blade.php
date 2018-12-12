@servers(['web' => "deployer@{$SERVER_IP}"])

@setup
    // $SERVER_IP - the ip address of the server passed in from gitlab ci file
    // $GITLAB_SECRET - access token passed in from gitlab ci file
    // $APP_NAME - the name of the actual application directory passed in from gitlab ci file
    // $GIT_URL - the path to git pull the repository passed in from gitlab ci file 
    $repository = $GIT_URL;
    $releases_dir = '/srv/releases';
    $app_dir = '/srv/app';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir .'/'. $release;
    $release_mount = $releases_dir . '/' . $release . '/' . $APP_NAME;
@endsetup

@story('deploy')
    clone_repository
    login_to_gitlab_registry
    pull_latest_images
    install_composer_dependencies
    install_npm_dependencies
    copy_files
    update_symlinks
    build_containers
    run_migrations
    update_permissions
    prune_old_images
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }}
@endtask

@task('login_to_gitlab_registry')
    echo 'Logging into docker registry'
    docker login -u koleda -p {{ $GITLAB_SECRET }} registry.gitlab.com
@endtask

@task('pull_latest_images')
    echo 'Pulling all images from docker registry'
    cd {{ $new_release_dir }}/build
    docker-compose -f docker-compose.prod.yml pull
@endtask

@task('install_composer_dependencies')
    echo "Starting deployment ({{ $release }})" 
    cd {{ $new_release_dir }}
    export APP_MOUNT={{ $release_mount }}
    echo "Running composer install"
    docker-compose -f build/docker-compose.prod.yml run --rm --user 1002 php-fpm composer install --prefer-dist --no-scripts -q -o
@endtask

@task('install_npm_dependencies')
    echo "Running npm install and building assets"
    cd {{ $new_release_dir }}
    export APP_MOUNT={{ $release_mount }}
    docker-compose -f build/docker-compose.prod.yml run --rm -w /var/www/html node bash -c "npm install && npm run production"
@endtask

@task('copy_files')
    echo "Copying build/php/.env to application directory"
    cp {{ $new_release_dir }}/build/php/.env {{ $new_release_dir }}/{{ $APP_NAME }}/.env 

    echo "Copying build/php/Builder.php hotfix to application vendor directory"
    cp {{ $new_release_dir }}/build/php/Builder.php {{ $new_release_dir }}/{{ $APP_NAME }}/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php
@endtask

@task('update_symlinks')
    echo "Linking {{ $new_release_dir }} -> {{ $app_dir }}" 
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}
    chgrp -h www-data {{ $app_dir }}
@endtask

{{-- Build containers and mount the newly linked app directory to them  --}}
@task('build_containers')
    echo 'Building new containers'
    cd {{ $app_dir }}/build

    export APP_MOUNT={{ $app_dir }}/{{ $APP_NAME }}/
    docker-compose -f docker-compose.prod.yml down && \
    docker-compose -f docker-compose.prod.yml up -d 
@endtask

@task('run_migrations')
    echo "Running php artisan migrate"
    cd {{ $app_dir }}/build
    docker-compose -f docker-compose.prod.yml exec -T php-fpm bash -c "php artisan migrate --force;exit"
@endtask

@task('update_permissions')
    echo "Updating app directory permissions" 
    cd {{ $app_dir }}/{{ $APP_NAME }}
    sudo chown -R deployer:www-data storage/ node_modules/
    sudo chmod -R 2770 storage/
  
    echo "Updating build directory permissions" 
    {{-- Then restrict permission on the build files --}}
    cd {{ $app_dir }}
    sudo chown -R root:ec2-user build
    sudo chmod -R 770 build
@endtask
 
@task('prune_old_images')
  echo "Removing unused docker images"
  echo y | docker image prune
@endtask

{{-- Not part of the story but useful for testing  --}} 
@task('remove_old_builds')
  docker rm -f $(docker ps -aq)
  docker volume rm $(docker volume ls -q)
  yes | sudo rm -r {{ $releases_dir }}
  rm {{ $app_dir }}
@endtask