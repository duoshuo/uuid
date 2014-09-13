<?php
namespace Uuid;
/**
 * The goods are here: www.ietf.org/rfc/rfc4122.txt.
 */
class Uuid {
	// A grand day! 100's nanoseconds precision at 00:00:00.000 15 Oct 1582.
	const START_EPOCH = -122192928000000000;
	
	const VERSION_TIME_BASED = 1;
	const VERSION_DCE_SECURITY = 2;
	const VERSION_MD5_HASHING = 3;
	const VERSION_RANDOM = 4;
	const VERSION_SHA1_HASHING = 5;
	
	const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
	const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
	const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
	const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';
	
	protected static $_semKey = 1000;
	protected static $_shmKey = 2000;

	protected static $_clockSeqKey = 1;
	protected static $_lastNanosKey = 2;

	/**
	 * 
	 * @var int
	 */
	protected static $_nodeId;

	/**
	 * 
	 * @var int
	 */
	protected static $_clockSeq;
	
	/**
	 * 
	 * @param string $mac
	 */
	public static function setMAC($mac) {
		self::$_nodeId = hexdec(str_replace(':', '', $mac));
	}
	
	/**
	 * 
	 * @param int $clockSeq
	 */
	public static function setClockSeq($clockSeq){
		self::$_clockSeq = $clockSeq;
	}

	/**
	 * 
	 * @param int $semKey
	 */
	public static function setSemKey($semKey) {
		self::$_semKey = $semKey;
	}

	/**
	 * 
	 * @param int $shmKey
	 */
	public static function setShmKey($shmKey) {
		self::$_shmKey = $shmKey;
	}
	
	protected static function _initClockSeq(){
		$shmId = shm_attach(self::$_shmKey);
		self::$_clockSeq = shm_get_var($shmId, self::$_clockSeqKey);
		
		if (self::$_clockSeq === false) {
			$semId = sem_get(self::$_semKey);
			sem_acquire($semId); //blocking
		
			if (shm_has_var($shmId, self::$_clockSeqKey)) {
				self::$_clockSeq = shm_get_var($shmId, self::$_clockSeqKey);
			}
			else {
				// 0x8000 variant (2 bits)
				// clock sequence (14 bits)
				self::$_clockSeq = 0x8000 | mt_rand(0, (1 << 14) - 1);
					
				shm_put_var($shmId, self::$_clockSeqKey, self::$_clockSeq);
			}
		
			sem_release($semId);
		}
		
		shm_detach($shmId);
	}
	
	/**
	 * 
	 * @param int $timestamp
	 * @param int $clockSeq
	 * @param int $nodeId
	 * @return string
	 */
	public static function fromFields($timestamp, $clockSeq, $nodeId){
		return sprintf(
				'%08x-%04x-%04x-%04x-%012x',
				$timestamp & 0xffffffff,
				$timestamp >> 32 & 0xffff,
				$timestamp >> 48 & 0x0fff | self::VERSION_TIME_BASED << 12,
				$clockSeq,
				$nodeId
			);
	}
	
	/**
	 * 
	 * @param string $hash
	 * @param int $version
	 * @return string
	 */
	public static function fromHashedName($hash, $version){
		// Set the version number
		$timeHi = hexdec(substr($hash, 12, 4)) & 0x0fff;
		$timeHi |= $version << 12;
		
		// Set the variant to RFC 4122
		$clockSeqHi = hexdec(substr($hash, 16, 2)) & 0x3f;
		$clockSeqHi |= 0x80;
		
		return sprintf(
				'%08s-%04s-%04s-%02s%02s-%012s',
				substr($hash, 0, 8),	// time_low
				substr($hash, 8, 4),	// time_mid
				sprintf('%04x', $timeHi),// time_hi_and_version
				sprintf('%02x', $clockSeqHi),// clock_seq_hi_and_reserved
				substr($hash, 18, 2),	// clock_seq_low
				substr($hash, 20, 12)	// node
			);
	}

	/**
	 * 
	 * @param int $sec
	 * @param int $msec
	 * @return string
	 */
	public static function fromTimestamp($sec, $msec = null) {
		if (self::$_clockSeq === null)
			self::_initClockSeq();
		
		$nanos = $sec * 10000000 + (isset($msec) ? $msec * 10 + mt_rand(0, 9) : mt_rand(0, 9999999));
		
		return self::fromFields($nanos - self::START_EPOCH, self::$_clockSeq, self::$_nodeId);
	}
	
	public static function now(){
		if (self::$_clockSeq === null)
			self::_initClockSeq();
		
		$timeOfDay = gettimeofday();
		$nanos = $timeOfDay['sec'] * 10000000 + $timeOfDay['usec'] * 10;

		$nanosSince = $nanos - self::START_EPOCH;

		$semId = sem_get(self::$_semKey);
		sem_acquire($semId); //blocking

		$shmId = shm_attach(self::$_shmKey);
		$lastNanos = shm_get_var($shmId, self::$_lastNanosKey);
		if ($lastNanos === false)
			$lastNanos = 0;

		if ($nanosSince > $lastNanos)
			$lastNanos = $nanosSince;
		else
			$nanosSince = ++$lastNanos;

		shm_put_var($shmId, self::$_lastNanosKey, $lastNanos);
		shm_detach($shmId);

		sem_release($semId);

		return self::fromFields($nanosSince, self::$_clockSeq, self::$_nodeId);
	}
	
	/**
	 * 
	 * @param string $hash
	 * @return string
	 */
	public static function fromMd5($md5){
		return self::fromHashedName($md5, self::VERSION_MD5_HASHING);
	}
	
	/**
	 * 
	 * @return string
	 */
	public static function fromRandom(){
		if (function_exists('openssl_random_pseudo_bytes')){
			$bytes = openssl_random_pseudo_bytes(16);
		}
		else{
			$bytes = '';
			for($i = 0; $i < 16; ++$i)
				$bytes .= chr(mt_rand(0, 255));
		}
		
		return self::fromHashedName(bin2hex($bytes), self::VERSION_RANDOM);
	}
	
	/**
	 *
	 * @param string $hash
	 * @return string
	 */
	public static function fromSha1($hash){
		return self::fromHashedName($hash, self::VERSION_SHA1_HASHING);
	}
}
