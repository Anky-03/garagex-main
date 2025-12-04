<?php
if (!function_exists('valid_lock_resources')) {
    function valid_lock_resources(): array
    {
        return ['user_crud', 'admin_car_create'];
    }

    function is_valid_lock_resource(string $resource): bool
    {
        return in_array($resource, valid_lock_resources(), true);
    }

    function cleanup_expired_locks(mysqli $conn, int $ttlSeconds): void
    {
        $threshold = date('Y-m-d H:i:s', time() - $ttlSeconds);
        $cutoff = mysqli_real_escape_string($conn, $threshold);
        mysqli_query($conn, "DELETE FROM locks WHERE locked_at < '$cutoff'");
    }

    function acquire_resource_lock(mysqli $conn, string $resource, int $userId, int $ttlSeconds = 300): bool
    {
        if (!is_valid_lock_resource($resource)) {
            return false;
        }

        cleanup_expired_locks($conn, $ttlSeconds);

        $resourceDb = mysqli_real_escape_string($conn, $resource);
        $now = mysqli_real_escape_string($conn, date('Y-m-d H:i:s'));
        $userId = intval($userId);

        $insert = "INSERT INTO locks (resource, user_id, locked_at) VALUES ('$resourceDb', $userId, '$now')";
        if (mysqli_query($conn, $insert)) {
            return true;
        }

        if (mysqli_errno($conn) === 1062) {
            $ownerSql = "SELECT user_id FROM locks WHERE resource = '$resourceDb'";
            $ownerResult = mysqli_query($conn, $ownerSql);
            if ($ownerResult && $owner = mysqli_fetch_assoc($ownerResult)) {
                if (intval($owner['user_id']) === $userId) {
                    mysqli_query($conn, "UPDATE locks SET locked_at = '$now' WHERE resource = '$resourceDb'");
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    function release_resource_lock(mysqli $conn, string $resource, int $userId): void
    {
        if (!is_valid_lock_resource($resource)) {
            return;
        }

        $resourceDb = mysqli_real_escape_string($conn, $resource);
        $userId = intval($userId);
        mysqli_query($conn, "DELETE FROM locks WHERE resource = '$resourceDb' AND user_id = $userId");
    }

    function touch_resource_lock(mysqli $conn, string $resource, int $userId): bool
    {
        if (!is_valid_lock_resource($resource)) {
            return false;
        }

        $resourceDb = mysqli_real_escape_string($conn, $resource);
        $userId = intval($userId);
        $now = mysqli_real_escape_string($conn, date('Y-m-d H:i:s'));

        $update = "UPDATE locks SET locked_at = '$now' WHERE resource = '$resourceDb' AND user_id = $userId";
        mysqli_query($conn, $update);
        return mysqli_affected_rows($conn) > 0;
    }

    function get_lock_holder(mysqli $conn, string $resource): ?array
    {
        if (!is_valid_lock_resource($resource)) {
            return null;
        }

        $resourceDb = mysqli_real_escape_string($conn, $resource);
        $sql = "SELECT l.user_id, u.nombre FROM locks l LEFT JOIN usuarios u ON u.id = l.user_id WHERE l.resource = '$resourceDb' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            return $row;
        }

        return null;
    }
}
