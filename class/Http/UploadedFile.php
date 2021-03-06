<?php
namespace Slime\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    /** @var int */
    protected $iErr;

    /** @var bool */
    protected $bMoved;

    /** @var StreamInterface */
    protected $Stream;

    /** @var string */
    protected $sFile;

    /** @var int */
    protected $iSize;

    /** @var string */
    protected $nsClientFilename;

    /** @var string */
    protected $nsClientMediaType;

    /**
     * @param string|resource|StreamInterface $srStreamOrFile
     * @param int                             $iSize
     * @param int                             $iErr
     * @param string|null                     $nsClientFilename
     * @param string|null                     $nsClientMediaType
     *
     * @throws InvalidArgumentException
     */
    public function __construct($srStreamOrFile, $iSize, $iErr, $nsClientFilename = null, $nsClientMediaType = null)
    {
        if ($iErr === UPLOAD_ERR_OK) {
            if (is_string($srStreamOrFile)) {
                $this->sFile = $srStreamOrFile;
            } elseif (is_resource($srStreamOrFile)) {
                $this->Stream = new Stream($srStreamOrFile);
            } elseif ($srStreamOrFile instanceof StreamInterface) {
                $this->Stream = $srStreamOrFile;
            } else {
                throw new InvalidArgumentException('Invalid stream or file provided for UploadedFile');
            }
        }
        $this->iSize             = (int)$iSize;
        $this->iErr              = (int)$iErr;
        $this->nsClientFilename  = $nsClientFilename;
        $this->nsClientMediaType = $nsClientMediaType;
    }

    /**
     * Retrieve a stream representing the uploaded file.
     *
     * This method MUST return a StreamInterface instance, representing the
     * uploaded file. The purpose of this method is to allow utilizing native PHP
     * stream functionality to manipulate the file upload, such as
     * stream_copy_to_stream() (though the result will need to be decorated in a
     * native PHP stream wrapper to work with such functions).
     *
     * If the moveTo() method has been called previously, this method MUST raise
     * an exception.
     *
     * @return StreamInterface Stream representation of the uploaded file.
     * @throws \RuntimeException in cases when no stream is available or can be
     *     created.
     */
    public function getStream()
    {
        if ($this->iErr !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->bMoved) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }

        if (!$this->Stream instanceof StreamInterface) {
            $this->Stream = new Stream($this->sFile);
        }

        return $this->Stream;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Use this method as an alternative to move_uploaded_file(). This method is
     * guaranteed to work in both SAPI and non-SAPI environments.
     * Implementations must determine which environment they are in, and use the
     * appropriate method (move_uploaded_file(), rename(), or a stream
     * operation) to perform the operation.
     *
     * $targetPath may be an absolute path, or a relative path. If it is a
     * relative path, resolution should be the same as used by PHP's rename()
     * function.
     *
     * The original file or stream MUST be removed on completion.
     *
     * If this method is called more than once, any subsequent calls MUST raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     *
     * @param string $targetPath Path to which to move the uploaded file.
     *
     * @throws \InvalidArgumentException if the $path specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     */
    public function moveTo($targetPath)
    {
        if ($this->iErr !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if (!is_string($targetPath) || $targetPath === '') {
            throw new InvalidArgumentException(
                'Invalid path provided for move operation'
            );
        }

        if ($this->bMoved) {
            throw new RuntimeException('Cannot move file; already moved!');
        }

        switch (PHP_SAPI) {
            case 'cli':
            case (empty($sapi) || 0 === strpos($sapi, 'cli') || !$this->sFile):
                // Non-SAPI environment, or no filename present
                $this->writeFile($targetPath);
                break;
            default:
                // SAPI environment, with file present
                if (false === move_uploaded_file($this->sFile, $targetPath)) {
                    throw new RuntimeException('Error occurred while moving uploaded file');
                }
                break;
        }

        $this->bMoved = true;
    }

    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize()
    {
        return $this->iSize;
    }

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, this method MUST return
     * UPLOAD_ERR_OK.
     *
     * Implementations SHOULD return the value stored in the "error" key of
     * the file in the $_FILES array.
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError()
    {
        return $this->iErr;
    }

    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "name" key of
     * the file in the $_FILES array.
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename()
    {
        return $this->nsClientFilename;
    }

    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "type" key of
     * the file in the $_FILES array.
     *
     * @return string|null The media type sent by the client or null if none
     *     was provided.
     */
    public function getClientMediaType()
    {
        return $this->nsClientMediaType;
    }

    /**
     * Write internal stream to given path
     *
     * @param string $sPath
     */
    private function writeFile($sPath)
    {
        $mHandle = fopen($sPath, 'wb+');
        if ($mHandle === false) {
            throw new RuntimeException('Unable to write to designated path');
        }

        $Stream = $this->getStream();
        $Stream->rewind();
        while (!$Stream->eof()) {
            fwrite($mHandle, $Stream->read(4096));
        }

        fclose($mHandle);
    }
}
