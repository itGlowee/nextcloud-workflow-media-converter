<?php

namespace OCA\WorkflowMediaConverter\Service;

use OCP\Files\File;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class ConversionValidationService {
	public function __construct(
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Validates if a file should be converted based on the conversion rules.
	 *
	 * @param File $file The file to check
	 * @param string $outputExtension The target extension for the output file
	 * @param string $postConversionSourceRule What to do with source file (keep/move/delete)
	 * @param string|null $postConversionSourceRuleMoveFolder Where to move source file
	 * @param string $postConversionOutputRule What to do with output file (keep/move)
	 * @param string|null $postConversionOutputRuleMoveFolder Where to move output file
	 * @param string $postConversionOutputConflictRule What to do if output file exists (keep/overwrite/move)
	 * @param string|null $postConversionOutputConflictRuleMoveFolder Where to move conflicting output file
	 *
	 * @return array{shouldConvert: bool, reason: string}
	 */
	public function shouldConvertFile(
		File $file,
		string $outputExtension,
		string $postConversionSourceRule,
		?string $postConversionSourceRuleMoveFolder,
		string $postConversionOutputRule,
		?string $postConversionOutputRuleMoveFolder,
		string $postConversionOutputConflictRule,
		?string $postConversionOutputConflictRuleMoveFolder,
	): array {
		// Build expected output file path
		$filenameNoExtension = pathinfo($file->getName(), PATHINFO_FILENAME);
		$possibleOutputFilename = $filenameNoExtension . '.' . strtolower($outputExtension);
		$sourceTargetPath = $postConversionSourceRuleMoveFolder
			? $postConversionSourceRuleMoveFolder . '/' . $file->getName()
			: null;

		$outputFilePath = $postConversionOutputRuleMoveFolder
			? $postConversionOutputRuleMoveFolder . '/' . $possibleOutputFilename
			: dirname($file->getPath()) . '/' . $possibleOutputFilename;

		// Validate basic configuration
		if (empty($outputExtension) || empty($postConversionSourceRule) || empty($postConversionOutputRule)) {
			return [
				'shouldConvert' => false,
				'reason' => 'Invalid configuration: missing required parameters'
			];
		}

		// Check if output file already exists and handle conflicts
		if ($this->rootFolder->nodeExists($outputFilePath)) {
			if ($postConversionOutputConflictRule === 'keep') {
				return [
					'shouldConvert' => false,
					'reason' => 'Output file already exists and conflict rule is "keep"'
				];
			}
			// If overwrite or move, conversion can proceed
		}

		// Validate source and output rule combinations
		$validationResult = $this->validateRuleCombination(
			$postConversionSourceRule,
			$postConversionOutputRule,
			$postConversionSourceRuleMoveFolder,
			$postConversionOutputRuleMoveFolder,
			$sourceTargetPath,
			$outputFilePath,
			$possibleOutputFilename
		);

		return $validationResult;
	}

	/**
	 * Validates the combination of source and output rules to ensure they're compatible.
	 * Only blocks critical issues - lets other edge cases fail gracefully at runtime.
	 *
	 * @param string $sourceRule
	 * @param string $outputRule
	 * @param string|null $sourceRuleMoveFolder
	 * @param string|null $outputRuleMoveFolder
	 * @param string|null $sourceTargetPath
	 * @param string $outputFilePath
	 * @param string $possibleOutputFilename
	 *
	 * @return array{shouldConvert: bool, reason: string}
	 */
	private function validateRuleCombination(
		string $sourceRule,
		string $outputRule,
		?string $sourceRuleMoveFolder,
		?string $outputRuleMoveFolder,
		?string $sourceTargetPath,
		string $outputFilePath,
		string $possibleOutputFilename,
	): array {
		// Check if source and output would collide at the same path
		if ($sourceTargetPath !== null && $sourceTargetPath === $outputFilePath) {
			return [
				'shouldConvert' => false,
				'reason' => 'Source and output would be moved to the same path'
			];
		}

		// Check if already converted (source moved to destination means it was processed)
		if ($sourceRule === 'move' && $sourceRuleMoveFolder && $outputRule === 'keep') {
			$sourceInMoveFolder = $sourceRuleMoveFolder . '/' . $possibleOutputFilename;
			if ($this->rootFolder->nodeExists($sourceInMoveFolder)) {
				return [
					'shouldConvert' => false,
					'reason' => 'Source already moved (previously converted)'
				];
			}
		}

		// Allow conversion - let invalid configurations fail gracefully at runtime
		return [
			'shouldConvert' => true,
			'reason' => 'Validation passed'
		];
	}
}
