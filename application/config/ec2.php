<?php
require __dir__.'/../../vendor/autoload.php';
use Aws\Ec2\Ec2Client;
$client = Ec2Client::factory(array(
    'region'  => 'us-east-1'
));

$ec2s = array();

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Filter EC2 instances by their names.
$result = $client->describeInstances(array());
$reservations = $result['Reservations'];
foreach($reservations as $reservation) {
    $instances = $reservation['Instances'];
    foreach($instances as $instance) {
        if(!array_key_exists('Tags', $instance)) {
            continue;
        }
        $tags = $instance['Tags'];
        foreach($tags as $tag) {
            if ($tag['Key'] == 'Name') {
                if ($filter === '' || stripos($tag['Value'], $filter) !== false) {
                    $ec2s[$instance['InstanceId']] = $instance;
                    break;
                }
            }
        }
    }
}

// Further refine the list of EC2 servers while building the master list
// with additional metadata from AWS.
$supervisor_servers = array();
foreach($ec2s as $ec2) {
    // https://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.Ec2.Ec2Client.html#_describeInstances
    if(!array_key_exists('PrivateIpAddress', $ec2)) {
        continue;
    }
    $tags = $ec2['Tags'];
    $found = 0;
    foreach($tags as $tag) {
        if ($tag['Key'] == 'Environment') {
            $found += 1;
        } else if ($tag['Key'] == 'Component') {
            $found += 1;
        }
    }
    if($found !== 2) {
        continue;
    }
    $public_ip_address = $ec2['PrivateIpAddress'];
    if(array_key_exists('PublicIpAddress', $ec2)) {
        $public_ip_address = $ec2['PublicIpAddress'];
    }
    $server = array(
        'url' => 'http://' . $ec2['PrivateIpAddress'] . '/RPC2',
        'public_url' => 'http://' . $public_ip_address . '/RPC2',
        'port' => '9100',
        'username' => $supervisor_username,
        'password' => $supervisor_password,
        'state' => $ec2['State']['Name'],
    );
    if(array_key_exists('StateReason', $ec2)) {
        $server['state_reason'] = $ec2['StateReason']['Message'];
    }
    $name = '';
    $tags = $ec2['Tags'];
    foreach($tags as $tag) {
        if ($tag['Key'] == 'Environment') {
            $server['environment'] = $tag['Value'];
        } else if($tag['Key'] == 'Component') {
            $server['component'] = $tag['Value'];
        } else if($tag['Key'] == 'Name') {
            $name = $tag['Value'];
        }
    }
    if($name == '') {
        $name = $ec2['InstanceId'];
    }
    $supervisor_servers[$name] = $server;
}
