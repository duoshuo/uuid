<?php
namespace Uuid;
/**
 * The goods are here: www.ietf.org/rfc/rfc4122.txt.
 */
class TimeUUID {
	// A grand day! 100's nanoseconds precision at 00:00:00.000 15 Oct 1582.
	const START_EPOCH = -122192928000000000;
	
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
	 * @param int $sec
	 * @param int $msec
	 * @return string
	 */
	public static function createFromSeconds($sec, $msec = null) {
		if (self::$_clockSeq === null)
			self::_initClockSeq();
		
		$nanos = $sec * 10000000 + (isset($msec) ? $msec * 10 + mt_rand(0, 9) : mt_rand(0, 9999999));
		
		return new self($nanos - self::START_EPOCH);
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

		return new self($nanosSince);
	}

	/**
	 * @var int
	 */
	protected $_nanosSince;

	/**
	 * 
	 * @param int $nanosSince
	 */
	public function __construct($nanosSince){
		$this->_nanosSince = $nanosSince;
	}
	
	/**
	 * @return string
	 */
	public function __toString(){
		return sprintf(
				'%08x-%04x-%04x-%04x-%012x',
				$this->_nanosSince & 0xffffffff,
				$this->_nanosSince >> 32 & 0xffff,
				$this->_nanosSince >> 48 & 0x0fff | 0x1000,
				self::$_clockSeq,
				self::$_nodeId
			);
	}
}
