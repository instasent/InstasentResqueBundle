# Intro to InstasentResqueBundle

> This is a fork from https://github.com/michelsalib/InstasentResqueBundle in order to keep its maintenance.

The Instasent resque bundle provides integration of [php-resque](https://github.com/chrisboulton/php-resque/) to Symfony. It is inspired from resque, a Redis-backed Ruby library for creating background jobs, placing them on multiple queues, and processing them later.

## Features:

- Creating a Job, with container access in order to leverage your Symfony services
- Enqueue a Job with parameters on a given queue
- Creating background worker on a given queue
- A UX to monitor your queues, workers and job statuses
- ability to schedule jobs to run at a specific time or after a number of seconds delay
- ability to auto re-queue failed jobs, with back-off strategies

TODOs:
- Log management
- Job status tracking
- Redis configuration
- Localisation
- Tests

## Screenshots
### Dashboard
![](https://github.com/instasent/InstasentResqueBundle/raw/master/Resources/screens/home.png)

## Installation and configuration:

### Requirements

Make sure you have redis installed on your machine: http://redis.io/

### Get the bundle

Add to your `instasent-resque-bundle` to your dependencies:

``` json
{
    "require": {
        ...
        "instasent/resque-bundle": "dev-master"
    }
    ...
}
```

To install, run `php composer.phar [update|install]`.

### Add InstasentResqueBundle to your application kernel

``` php
<?php

    // app/AppKernel.php
    public function registerBundles()
    {
        return array(
            // ...
            new Instasent\ResqueBundle\InstasentResqueBundle(),
            // ...
        );
    }
```

### Import the routing configuration

Add to your `routing.yml`:

``` yml
# app/config/routing.yml
InstasentResqueBundle:
    resource: "@InstasentResqueBundle/Resources/config/routing.xml"
    prefix:   /resque
```

You can customize the prefix as you wish.

You can now access the dashboard at this url: `/resque`

To secure the dashboard, you can add the following to your `security.yml` - provided your administrator role is `ROLE_ADMIN`

```yml
    access_control:
        - { path: ^/resque, roles: ROLE_ADMIN }
```

### Optional, secure the dashboard behind a role


Add to your `security.yml`:

``` yml
# app/config/security.yml
access_control:
    - { path: ^/resque, roles: ROLE_ADMIN }
```

Now only users with the role ROLE_ADMIN will be able to access the dashboard at this url: `/resque`

### Optional, set configuration

You may want to add some configuration to your `config.yml`

``` yml
# app/config/config.yml
instasent_resque:
    class: Instasent\ResqueBundle\Resque           # the resque class if different from default
    vendor_dir: %kernel.root_dir%/../vendor  # the vendor dir if different from default
    prefix: my-resque-prefix                 # optional prefix to separate Resque data per site/app
    redis:
        host: localhost                      # the redis host
        port: 6379                           # the redis port
        database: 1                          # the redis database
    auto_retry: [0, 10, 60]                  # auto retry failed jobs
    worker:
        root_dir: path/to/worker/root        # the root dir of app that run workers (optional)
```

See the [Auto retry](#auto-retry) section for more on how to use `auto_retry`.

Set `worker: root_dir:` in case job fails to run when worker systems are hosted on separate server/dir from the system creating the queue.
When running multiple configured apps for multiple workers, all apps must be able to access by the same root_dir defined in `worker: root_dir`.

### Optional, configure lazy loading

This bundle is prepared for lazy loading in order to make a connection to redis only when its really used. Symfony2 supports that starting with 2.3. To make it work an additional step needs to be done. You need to install a proxy manager to your Symfony2 project. The full documentation for adding the proxy manager can be found in [Symfony2's Lazy Service documentation](http://symfony.com/doc/current/components/dependency_injection/lazy_services.html).

## Creating a Job

A job is a subclass of the `Instasent\ResqueBundle\Job` class. You also can use the `Instasent\Resque\ContainerAwareJob` if you need to leverage the container during job execution.
You will be forced to implement the run method that will contain your job logic:

``` php
<?php

namespace My;

use Instasent\ResqueBundle\Job;

class MyJob extends Job
{
    public function run($args)
    {
        \file_put_contents($args['file'], $args['content']);
    }
}
```

As you can see you get an $args parameter that is the array of arguments of your Job.

## Adding a job to a queue

You can get the resque service simply by using the container. From your controller you can do:

``` php
<?php

// get resque
$resque = $this->get('instasent_resque.resque');

// create your job
$job = new MyJob();
$job->args = array(
    'file'    => '/tmp/file',
    'content' => 'hello',
);

// enqueue your job
$resque->enqueue($job);
```

## Running a worker on a queue

Executing the following commands will create a work on :
- the `default` queue : `app/console instasent:resque:worker-start default`
- the `q1` and `q2` queue : `app/console instasent:resque:worker-start q1,q2` (separate name with `,`)
- all existing queues : `app/console instasent:resque:worker-start "*"`

You can also run a worker foreground by adding the `--foreground` option;

By default `VERBOSE` environment variable is set when calling php-resque
- `--verbose` option sets `VVERBOSE`
- `--quiet` disables both so no debug output is thrown

See php-resque logging option : https://github.com/chrisboulton/php-resque#logging

## Use monolog from your kernel

You can pass a monolog channel to write your logs, see example in supervisor config. Note: Pass Kernel ENV to resque is to instanciate logger, no for sharing kernel between jobs.

## Running a worker without forking

Sometimes intensive tasks may lose performance with forking mode. We have added a bin/resque-single binary, you can pass kernel file to be loaded only once at start. Be careful cause if this Worker fails, then you need to restart it manually. We recommend using supervisor to control this behaviour, and superlance to restart worker when memory hits some threshold.

Pay special attention to your code, shared variables, service instances, etc.

## Adding a delayed job to a queue

You can specify that a job is run at a specific time or after a specific delay (in seconds).

From your controller you can do:

``` php
<?php

// get resque
$resque = $this->get('instasent_resque.resque');

// create your job
$job = new MyJob();
$job->args = array(
    'file'    => '/tmp/file',
    'content' => 'hello',
);

// enqueue your job to run at a specific \DateTime or int unix timestamp
$resque->enqueueAt(\DateTime|int $at, $job);

// or

// enqueue your job to run after a number of seconds
$resque->enqueueIn($seconds, $job);

```

You must also run a `scheduledworker`, which is responsible for taking items out of the special delayed queue and putting
them into the originally specified queue.

`app/console instasent:resque:scheduledworker-start`

Stop it later with `app/console instasent:resque:scheduledworker-stop`.

Note that when run in background mode (default) it creates a PID file in 'cache/<environment>/instasent_resque_scheduledworker.pid'. If you
clear your cache while the scheduledworker is running you won't be able to stop it with the `scheduledworker-stop` command.

Alternatively, you can run the scheduledworker in the foreground with the `--foreground` option.

Note also you should only ever have one scheduledworker running, and if the PID file already exists you will have to use 
the `--force` option to start a scheduledworker.

## Manage production workers with supervisord

It's probably best to use supervisord (http://supervisord.org) to run the workers in production, rather than re-invent job
spawning, monitoring, stopping and restarting.

#### Sample conf files

```ini
[program:myapp_phpresque_default]
command = /usr/bin/php /home/sites/myapp/prod/current/vendor/instasent/resque-bundle/bin/resque
user = myusername
environment = APP_INCLUDE='/home/sites/myapp/prod/current/vendor/autoload.php',VERBOSE='1',QUEUE='default'
stopsignal=QUIT

[program:myapp_phpresque_default_single_worker]
command = /usr/bin/php /home/sites/myapp/prod/current/vendor/instasent/resque-bundle/bin/resque-single
user = myusername
environment = APP_KERNEL='/home/sites/myapp/prod/current/app/AppKernel.php',APP_INCLUDE='/home/sites/myapp/prod/current/vendor/autoload.php',VERBOSE='1',QUEUE='default'
stopsignal=QUIT

[program:myapp_phpresque_scheduledworker]
command = /usr/bin/php /home/sites/myapp/prod/current/vendor/instasent/resque-bundle/bin/resque-scheduler
user = myusername
environment = APP_INCLUDE='/home/sites/myapp/prod/current/vendor/autoload.php',VERBOSE='1',RESQUE_PHP='/home/sites/myapp/prod/current/vendor/chrisboulton/php-resque/lib/Resque.php'
stopsignal=QUIT

[program:myapp_phpresque_default_single_worker_logger]
command = /usr/bin/php /home/sites/myapp/prod/current/vendor/instasent/resque-bundle/bin/resque-single
user = myusername
environment = APP_KERNEL='/home/sites/myapp/prod/current/app/AppKernel.php',LOG_CHANNEL='monolog.logger.custom',APP_INCLUDE='/home/sites/myapp/prod/current/vendor/autoload.php',VERBOSE='1',QUEUE='default'
stopsignal=QUIT

[group:myapp]
programs=myapp_phpresque_default,myapp_phpresque_scheduledworker
```

(If you use a custom Resque prefix, add an extra environment variable: PREFIX='my-resque-prefix')

```ini
[program:myapp_phpresque_default]
command = /usr/bin/php /home/sites/myapp/prod/current/bin/console instasent:resque:worker-start --verbose default
user = myusername
stopsignal=QUIT

[program:myapp_phpresque_default_single_worker]
command = /usr/bin/php /home/sites/myapp/prod/current/bin/console instasent:resque:worker-single-start --verbose default
user = myusername
stopsignal=QUIT

[program:myapp_phpresque_scheduledworker]
command = /usr/bin/php /home/sites/myapp/prod/current/bin/console instasent:resque:scheduledworker-start --verbose
user = myusername
stopsignal=QUIT

[program:myapp_phpresque_default_single_worker_logger]
command = /usr/bin/php /home/sites/myapp/prod/current/bin/console instasent:resque:worker-single-start --logging=monolog.logger.custom --verbose default
user = myusername
stopsignal=QUIT

[group:myapp]
programs=myapp_phpresque_default,myapp_phpresque_scheduledworker
```

When using Symfony Console commands review bundle configuration

Then in Capifony you can do

`sudo supervisorctl stop myapp:*` before deploying your app and `sudo supervisorctl start myapp:*` afterwards.

## More features

### Changing the queue

You can change a job queue just by setting the `queue` field of the job:

From within the job:

``` php
<?php

namespace My;

use Instasent\ResqueBundle\Job;

class MyJob extends Job
{
    public function __construct()
    {
        $this->queue = 'my_queue';
    }

    public function run($args)
    {
        ...
    }
}
```

Or from outside the job:

``` php
<?php

// create your job
$job = new MyJob();
$job->queue = 'my_queue';
```

### Access the container from inside your job

Just extend the `ContainerAwareJob`:

``` php
<?php

namespace My;

use Instasent\ResqueBundle\ContainerAwareJob;

class MyJob extends ContainerAwareJob
{
    public function run($args)
    {
        $doctrine = $this->getContainer()->getDoctrine();
        ...
    }
}
```

### Stop a worker

Use the `app/console instasent:resque:worker-stop` command.

- No argument will display running workers that you can stop.
- Add a worker id to stop it: `app/console instasent:resque:worker-stop ubuntu:3949:default`
- Add the `--all` option to stop all the workers.


### Auto retry

You can have the bundle auto retry failed jobs by adding `retry strategy` for either a specific job, or for all jobs in general:

The following will allow `Some\Job` to retry 3 times.

* right away
* after a 10 second delay
* after a 60 second delay

```yml
instasent_resque:
    redis:
        ....
    auto_retry:
        Some\Job: [0, 10, 60]
```

Setting strategy for all jobs:

```yml
instasent_resque:
    auto_retry: [0, 10, 60]
```

With default strategy for all but specific jobs:

```yml
instasent_resque:
    auto_retry:
      default:        [0, 10, 60]
        Some\Job:       [0, 10, 120, 240]
        Some\Other\Job: [10, 30, 120, 600]
```

The `default` strategy (if provided) will be applied to all jobs that does not have a specific strategy attached. If not provided these jobs will not have auto retry.

You can disable `auto_retry` for selected jobs by using an empty array:

```yml
instasent_resque:
    auto_retry:
      default:        [0, 10, 60]
        Some\Job:       []
        Some\Other\Job: [10, 30, 120, 600]
```

Here `Some\Job` will not have any `auto_retry` attached.

**Please note**

To use the `auto_retry` feature, you must also run the scheduler job:

`app/console instasent:resque:scheduledworker-start`
